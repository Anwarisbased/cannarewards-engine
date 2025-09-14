<?php
namespace CannaRewards\Repositories;

use CannaRewards\Domain\MetaKeys;
use CannaRewards\DTO\ShippingAddressDTO;
use CannaRewards\Infrastructure\WordPressApiWrapper;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * User Repository
 *
 * Handles all data access logic for users. This is the single source of truth
 * for fetching and persisting user data, abstracting away the underlying
 * WordPress user and usermeta table implementation.
 */
class UserRepository {
    private WordPressApiWrapper $wp;

    public function __construct(WordPressApiWrapper $wp) {
        $this->wp = $wp;
    }

    /**
     * Retrieves the core user object (\WP_User).
     * This is one of the few places where returning a WordPress-specific object is acceptable,
     * as it's the raw data source that services will adapt into DTOs.
     */
    public function getUserCoreData(int $user_id): ?\WP_User {
        return $this->wp->getUserById($user_id);
    }
    
    public function getUserCoreDataBy(string $field, string $value): ?\WP_User {
        return $this->wp->findUserBy($field, $value);
    }
    
    /**
     * Creates a new WordPress user.
     * @throws \Exception If user creation fails.
     * @return int The new user's ID.
     */
    public function createUser(string $email, string $password, string $firstName, string $lastName): int {
        $user_id = $this->wp->createUser([
            'user_login' => $email,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'role' => 'subscriber'
        ]);

        if (is_wp_error($user_id)) {
            throw new \Exception($user_id->get_error_message(), 500);
        }
        return (int) $user_id;
    }

    /**
     * Saves the initial meta fields for a newly registered user.
     */
    public function saveInitialMeta(int $userId, string $phone, bool $agreedToMarketing): void {
        $this->wp->updateUserMeta($userId, 'phone_number', $phone);
        $this->wp->updateUserMeta($userId, 'marketing_consent', $agreedToMarketing);
        $this->wp->updateUserMeta($userId, '_age_gate_confirmed_at', current_time('mysql', 1));
    }

    /**
     * A generic proxy to the wrapper for fetching user meta.
     * Services should use this instead of accessing the wrapper directly for user data.
     */
    public function getUserMeta(int $user_id, string $key, bool $single = true) {
        return $this->wp->getUserMeta($user_id, $key, $single);
    }

    public function getPointsBalance(int $user_id): int {
        $balance = $this->wp->getUserMeta($user_id, MetaKeys::POINTS_BALANCE, true);
        return empty($balance) ? 0 : (int) $balance;
    }

    public function getLifetimePoints(int $user_id): int {
        $lifetime_points = $this->wp->getUserMeta($user_id, MetaKeys::LIFETIME_POINTS, true);
        return empty($lifetime_points) ? 0 : (int) $lifetime_points;
    }

    public function getCurrentRankKey(int $user_id): string {
        $rank_key = $this->wp->getUserMeta($user_id, MetaKeys::CURRENT_RANK_KEY, true);
        return empty($rank_key) ? 'member' : $rank_key;
    }
    
    public function getReferralCode(int $user_id): ?string {
        $code = $this->wp->getUserMeta($user_id, MetaKeys::REFERRAL_CODE, true);
        return empty($code) ? null : $code;
    }

    public function findUserIdByReferralCode(string $referral_code): ?int {
        $users = $this->wp->findUsers([
            'meta_key'   => MetaKeys::REFERRAL_CODE,
            'meta_value' => sanitize_text_field($referral_code),
            'number'     => 1,
            'fields'     => 'ID',
        ]);
        return !empty($users) ? (int) $users[0] : null;
    }

    public function getReferringUserId(int $user_id): ?int {
        $referrer_id = $this->wp->getUserMeta($user_id, MetaKeys::REFERRED_BY_USER_ID, true);
        return !empty($referrer_id) ? (int) $referrer_id : null;
    }

    /**
     * Gets the user's shipping address as a formatted DTO.
     */
    public function getShippingAddressDTO(int $user_id): ShippingAddressDTO {
        return new ShippingAddressDTO(
            first_name: $this->wp->getUserMeta($user_id, 'shipping_first_name', true),
            last_name: $this->wp->getUserMeta($user_id, 'shipping_last_name', true),
            address_1: $this->wp->getUserMeta($user_id, 'shipping_address_1', true),
            city: $this->wp->getUserMeta($user_id, 'shipping_city', true),
            state: $this->wp->getUserMeta($user_id, 'shipping_state', true),
            postcode: $this->wp->getUserMeta($user_id, 'shipping_postcode', true)
        );
    }

    /**
     * Gets the user's shipping address as a simple associative array.
     */
    public function getShippingAddressArray(int $user_id): array {
        return (array) $this->getShippingAddressDTO($user_id);
    }

    public function savePointsAndRank(int $user_id, int $new_balance, int $new_lifetime_points, string $new_rank_key): void {
        $this->wp->updateUserMeta($user_id, MetaKeys::POINTS_BALANCE, $new_balance);
        $this->wp->updateUserMeta($user_id, MetaKeys::LIFETIME_POINTS, $new_lifetime_points);
        $this->wp->updateUserMeta($user_id, MetaKeys::CURRENT_RANK_KEY, $new_rank_key);
    }
    
    public function saveReferralCode(int $user_id, string $code): void {
        $this->wp->updateUserMeta($user_id, MetaKeys::REFERRAL_CODE, $code);
    }
    
    public function setReferredBy(int $new_user_id, int $referrer_user_id): void {
        $this->wp->updateUserMeta($new_user_id, MetaKeys::REFERRED_BY_USER_ID, $referrer_user_id);
    }
    
    public function saveShippingAddress(int $user_id, array $shipping_details): void {
        if (empty($shipping_details) || !isset($shipping_details['firstName'])) {
            return;
        }

        $meta_map = [
            'firstName' => 'shipping_first_name',
            'lastName'  => 'shipping_last_name',
            'address1'  => 'shipping_address_1',
            'city'      => 'shipping_city',
            'state'     => 'shipping_state',
            'zip'       => 'shipping_postcode',
        ];

        foreach ($meta_map as $frontend_key => $meta_key) {
            if (isset($shipping_details[$frontend_key])) {
                $this->wp->updateUserMeta($user_id, $meta_key, sanitize_text_field($shipping_details[$frontend_key]));
            }
        }
        
        $this->wp->updateUserMeta( $user_id, 'billing_first_name', sanitize_text_field( $shipping_details['firstName'] ?? '' ) );
        $this->wp->updateUserMeta( $user_id, 'billing_last_name', sanitize_text_field( $shipping_details['lastName'] ?? '' ) );
    }
    
    /**
     * Updates a user's core data (first name, last name, etc.).
     * @param int $user_id The user ID
     * @param array $data Associative array of user data to update
     * @return int|\WP_Error The updated user's ID on success, or a WP_Error object on failure.
     */
    public function updateUserData(int $user_id, array $data) {
        $data['ID'] = $user_id;
        return $this->wp->updateUser($data);
    }
    
    /**
     * Updates a user meta field.
     * @param int $user_id The user ID
     * @param string $meta_key The meta key to update
     * @param mixed $meta_value The meta value to set
     * @param mixed $prev_value Optional. Previous value to check before updating.
     * @return bool True on success, false on failure.
     */
    public function updateUserMetaField(int $user_id, string $meta_key, $meta_value, $prev_value = '') {
        return $this->wp->updateUserMeta($user_id, $meta_key, $meta_value, $prev_value);
    }
}