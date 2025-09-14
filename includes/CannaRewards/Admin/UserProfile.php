<?php
namespace CannaRewards\Admin;

// ARCHITECTURAL NOTE: This class exists within the Admin boundary.
// Direct calls to WordPress functions (e.g., get_post_meta, add_meta_box)
// are permitted here for pragmatic integration with the WordPress admin UI.
// This contrasts with the core application logic in Services/Repositories,
// which must remain pure.

use WP_User;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles custom fields on the WordPress User Profile screen.
 */
class UserProfile {

    public static function init() {
        add_action('show_user_profile', [self::class, 'add_custom_fields']);
        add_action('edit_user_profile', [self::class, 'add_custom_fields']);
        add_action('personal_options_update', [self::class, 'save_custom_fields']);
        add_action('edit_user_profile_update', [self::class, 'save_custom_fields']);
    }

    public static function add_custom_fields(WP_User $user) {
        ?>
        <h2>CannaRewards Custom Fields</h2>
        <table class="form-table" id="cannarewards-custom-fields">
            <tr>
                <th><label for="phone_number">Phone Number</label></th>
                <td><input type="text" id="phone_number" name="phone_number" value="<?php echo esc_attr(get_user_meta($user->ID, 'phone_number', true)); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="marketing_consent">Marketing Consent</label></th>
                <td>
                    <input type="checkbox" id="marketing_consent" name="marketing_consent" value="1" <?php checked(1, get_user_meta($user->ID, 'marketing_consent', true)); ?> />
                    <span class="description">User agreed to receive marketing communications.</span>
                </td>
            </tr>
        </table>
        
        <h3>Shipping Address</h3>
        <table class="form-table" id="cannarewards-shipping-fields">
             <tr>
                <th><label for="shipping_first_name">First Name</label></th>
                <td><input type="text" name="shipping_first_name" id="shipping_first_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_first_name', true)); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="shipping_last_name">Last Name</label></th>
                <td><input type="text" name="shipping_last_name" id="shipping_last_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_last_name', true)); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="shipping_address_1">Address Line 1</label></th>
                <td><input type="text" name="shipping_address_1" id="shipping_address_1" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_address_1', true)); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="shipping_city">City</label></th>
                <td><input type="text" name="shipping_city" id="shipping_city" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_city', true)); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="shipping_state">State</label></th>
                <td><input type="text" name="shipping_state" id="shipping_state" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_state', true)); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="shipping_postcode">ZIP / Postal Code</label></th>
                <td><input type="text" name="shipping_postcode" id="shipping_postcode" value="<?php echo esc_attr(get_user_meta($user->ID, 'shipping_postcode', true)); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php
    }

    public static function save_custom_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        $meta_to_save = [
            'phone_number', 
            'shipping_first_name', 
            'shipping_last_name', 
            'shipping_address_1', 
            'shipping_city', 
            'shipping_state', 
            'shipping_postcode'
        ];

        foreach ($meta_to_save as $key) {
            if (isset($_POST[$key])) {
                update_user_meta($user_id, $key, sanitize_text_field($_POST[$key]));
            }
        }

        $marketing_consent = isset($_POST['marketing_consent']) ? 1 : 0;
        update_user_meta($user_id, 'marketing_consent', $marketing_consent);
    }
}