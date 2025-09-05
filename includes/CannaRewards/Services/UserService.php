<?php
namespace CannaRewards\Services;

use CannaRewards\Includes\Event;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * User Service
 *
 * Handles all business logic related to fetching, creating, and updating user profiles and data.
 */
class UserService {
    private $cdp_service;
    private $action_log_service;

    public function __construct() {
        $this->cdp_service        = new CDPService();
        $this->action_log_service = new ActionLogService();
    }

    /**
     * Creates a new user, generates their referral code, and fires necessary events.
     * This is the single source of truth for user registration logic.
     *
     * @param array $user_data The registration data from the API request.
     * @return array The result of the registration.
     * @throws \Exception If registration fails, with a code indicating the error type.
     */
    public function create_user( array $user_data ): array {
        if (!get_option('users_can_register')) {
            // Use 503 Service Unavailable for disabled registration.
            throw new \Exception('User registration is currently disabled.', 503);
        }

        $email = sanitize_email($user_data['email'] ?? '');
        $password = $user_data['password'] ?? '';
        $first_name = sanitize_text_field($user_data['firstName'] ?? '');

        if (empty($email) || empty($password) || !is_email($email)) {
            throw new \Exception('A valid email and password are required.', 400);
        }
        if (email_exists($email)) {
            // Use code 1 for "conflict" type errors.
            throw new \Exception('An account with that email already exists.', 409);
        }

        $user_id = wp_insert_user([
            'user_login' => $email,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => $first_name,
            'last_name'  => sanitize_text_field($user_data['lastName'] ?? ''),
            'role'       => 'subscriber'
        ]);

        if (is_wp_error($user_id)) {
            throw new \Exception($user_id->get_error_message(), 500);
        }

        // --- Post-registration side-effects ---
        update_user_meta($user_id, 'phone_number', sanitize_text_field($user_data['phone'] ?? ''));
        update_user_meta($user_id, 'marketing_consent', !empty($user_data['agreedToMarketing']));
        update_user_meta($user_id, '_age_gate_confirmed_at', current_time('mysql', 1));
        
        // Generate a referral code for the new user.
        $referral_service = new ReferralService();
        $referral_service->generate_code_for_new_user($user_id, $first_name);

        $referral_code = sanitize_text_field($user_data['referralCode'] ?? null);
        
        // Broadcast the 'user_created' event for other services to listen to (e.g., ReferralService).
        Event::broadcast('user_created', [
            'user_id'       => $user_id,
            'referral_code' => $referral_code
        ]);
        
        $this->cdp_service->track($user_id, 'user_created', [
             'signup_method'      => 'password',
             'referral_code_used' => $referral_code
        ]);

        return [
            'success' => true,
            'message' => 'Registration successful. Please log in.',
            'userId' => $user_id
        ];
    }

    /**
     * Gets the minimal, lightweight data needed for an authenticated session.
     */
    public function get_user_session_data( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }
        $rank = get_user_current_rank( $user_id );

        return [
            'id'             => $user_id,
            'firstName'      => $user->first_name,
            'email'          => $user->user_email,
            'points_balance' => get_user_points_balance( $user_id ),
            'rank'           => [ 'key' => $rank['key'] ?? 'member', 'name' => $rank['name'] ?? 'Member' ],
            'onboarding_quest_step' => (int) get_user_meta( $user_id, '_onboarding_quest_step', true ) ?: 1,
            'feature_flags'  => new \stdClass(),
        ];
    }
    
    /**
     * Gets the dynamic data needed for the main user dashboard.
     */
    public function get_user_dashboard_data( int $user_id ): array {
        return [
            'lifetime_points' => get_user_lifetime_points( $user_id ),
        ];
    }

    /**
     * Gets the complete profile data for a user, including all custom fields.
     */
    public function get_full_profile_data( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }

        $custom_fields_definitions = $this->get_custom_fields_definitions();
        $custom_fields_values      = [];
        foreach ( $custom_fields_definitions as $field ) {
            $value = get_user_meta( $user_id, $field['key'], true );
            if ( ! empty( $value ) ) {
                $custom_fields_values[ $field['key'] ] = $value;
            }
        }
        
        $shipping_address = [
            'first_name' => get_user_meta($user_id, 'shipping_first_name', true),
            'last_name' => get_user_meta($user_id, 'shipping_last_name', true),
            'address_1' => get_user_meta($user_id, 'shipping_address_1', true),
            'city' => get_user_meta($user_id, 'shipping_city', true),
            'state' => get_user_meta($user_id, 'shipping_state', true),
            'postcode' => get_user_meta($user_id, 'shipping_postcode', true),
        ];

        return [
            'lastName'                  => $user->last_name,
            'phone_number'              => get_user_meta( $user_id, 'phone_number', true ),
            'referral_code'             => get_user_meta( $user_id, '_canna_referral_code', true ),
            'shipping_address'          => $shipping_address,
            'unlocked_achievement_keys' => [], // Placeholder for future slice
            'custom_fields'             => [
                'definitions' => $custom_fields_definitions,
                'values'      => (object) $custom_fields_values,
            ],
        ];
    }

    /**
     * Updates a user's profile with core and dynamic custom data.
     */
    public function update_user_profile( int $user_id, array $data ): array {
        $changed_fields = [];

        $core_user_data = ['ID' => $user_id];
        if ( isset( $data['firstName'] ) ) {
            $core_user_data['first_name'] = sanitize_text_field( $data['firstName'] );
            $changed_fields[] = 'firstName';
        }
        if ( isset( $data['lastName'] ) ) {
            $core_user_data['last_name'] = sanitize_text_field( $data['lastName'] );
            $changed_fields[] = 'lastName';
        }
        if (count($core_user_data) > 1) {
            wp_update_user($core_user_data);
        }

        if (isset($data['phone_number'])) {
            update_user_meta($user_id, 'phone_number', sanitize_text_field($data['phone_number']));
            $changed_fields[] = 'phone_number';
        }

        if ( isset( $data['custom_fields'] ) && is_array( $data['custom_fields'] ) ) {
            $definitions = $this->get_custom_fields_definitions();
            $allowed_keys = wp_list_pluck($definitions, 'key');
            
            foreach ( $data['custom_fields'] as $key => $value ) {
                if ( in_array( $key, $allowed_keys, true ) ) {
                    update_user_meta( $user_id, $key, sanitize_text_field( $value ) );
                    $changed_fields[] = 'custom_' . $key;
                }
            }
        }

        if (!empty($changed_fields)) {
            $log_meta_data = ['changed_fields' => $changed_fields];
            $this->action_log_service->record($user_id, 'profile_updated', 0, $log_meta_data);
            $this->cdp_service->track($user_id, 'user_profile_updated', $log_meta_data);
        }

        return $this->get_full_profile_data( $user_id );
    }

    /**
     * Private helper to get all custom field definitions, with caching.
     */
    private function get_custom_fields_definitions(): array {
        $cached_fields = get_transient('canna_custom_fields_definition');
        if (is_array($cached_fields)) {
            return $cached_fields;
        }

        $fields = [];
        $args = [
            'post_type'      => 'canna_custom_field',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];
        $field_posts = get_posts($args);

        foreach ($field_posts as $post) {
            $options_raw = get_post_meta($post->ID, 'options', true);
            $fields[] = [
                'key'       => get_post_meta($post->ID, 'meta_key', true),
                'label'     => get_the_title($post->ID),
                'type'      => get_post_meta($post->ID, 'field_type', true),
                'options'   => !empty($options_raw) ? preg_split('/\\r\\n|\\r|\\n/', $options_raw) : [],
                'display'   => (array) get_post_meta($post->ID, 'display_location', true),
            ];
        }

        set_transient('canna_custom_fields_definition', $fields, 12 * HOUR_IN_SECONDS);
        return $fields;
    }
}