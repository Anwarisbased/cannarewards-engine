<?php
namespace CannaRewards\Admin;

// ARCHITECTURAL NOTE: This class exists within the Admin boundary.
// Direct calls to WordPress functions (e.g., get_post_meta, add_meta_box)
// are permitted here for pragmatic integration with the WordPress admin UI.
// This contrasts with the core application logic in Services/Repositories,
// which must remain pure.

use WP_User;
use CannaRewards\Infrastructure\WordPressApiWrapper;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles custom fields on the WordPress User Profile screen.
 */
final class UserProfile {
    private WordPressApiWrapper $wp;
    private FieldFactory $fieldFactory;

    public function __construct(WordPressApiWrapper $wp, FieldFactory $fieldFactory) {
        $this->wp = $wp;
        $this->fieldFactory = $fieldFactory;
    }

    public function init(): void {
        add_action('show_user_profile', [$this, 'add_custom_fields']);
        add_action('edit_user_profile', [$this, 'add_custom_fields']);
        add_action('personal_options_update', [$this, 'save_custom_fields']);
        add_action('edit_user_profile_update', [$this, 'save_custom_fields']);
    }

    public function add_custom_fields(WP_User $user): void {
        ?>
        <h2>CannaRewards Custom Fields</h2>
        <table class="form-table" id="cannarewards-custom-fields">
            <tr>
                <th><label for="phone_number">Phone Number</label></th>
                <td>
                    <?php 
                    $this->fieldFactory->render_text_input(
                        'phone_number', 
                        $this->wp->getUserMeta($user->ID, 'phone_number', true), 
                        ['id' => 'phone_number', 'class' => 'regular-text']
                    ); 
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="marketing_consent">Marketing Consent</label></th>
                <td>
                    <?php 
                    $this->fieldFactory->render_checkbox(
                        'marketing_consent', 
                        (bool) $this->wp->getUserMeta($user->ID, 'marketing_consent', true), 
                        ['id' => 'marketing_consent', 'label' => 'User agreed to receive marketing communications.']
                    ); 
                    ?>
                </td>
            </tr>
        </table>
        
        <h3>Shipping Address</h3>
        <table class="form-table" id="cannarewards-shipping-fields">
             <tr>
                <th><label for="shipping_first_name">First Name</label></th>
                <td>
                    <?php 
                    $this->fieldFactory->render_text_input(
                        'shipping_first_name', 
                        $this->wp->getUserMeta($user->ID, 'shipping_first_name', true), 
                        ['id' => 'shipping_first_name', 'class' => 'regular-text']
                    ); 
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="shipping_last_name">Last Name</label></th>
                <td>
                    <?php 
                    $this->fieldFactory->render_text_input(
                        'shipping_last_name', 
                        $this->wp->getUserMeta($user->ID, 'shipping_last_name', true), 
                        ['id' => 'shipping_last_name', 'class' => 'regular-text']
                    ); 
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="shipping_address_1">Address Line 1</label></th>
                <td>
                    <?php 
                    $this->fieldFactory->render_text_input(
                        'shipping_address_1', 
                        $this->wp->getUserMeta($user->ID, 'shipping_address_1', true), 
                        ['id' => 'shipping_address_1', 'class' => 'regular-text']
                    ); 
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="shipping_city">City</label></th>
                <td>
                    <?php 
                    $this->fieldFactory->render_text_input(
                        'shipping_city', 
                        $this->wp->getUserMeta($user->ID, 'shipping_city', true), 
                        ['id' => 'shipping_city', 'class' => 'regular-text']
                    ); 
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="shipping_state">State</label></th>
                <td>
                    <?php 
                    $this->fieldFactory->render_text_input(
                        'shipping_state', 
                        $this->wp->getUserMeta($user->ID, 'shipping_state', true), 
                        ['id' => 'shipping_state', 'class' => 'regular-text']
                    ); 
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="shipping_postcode">ZIP / Postal Code</label></th>
                <td>
                    <?php 
                    $this->fieldFactory->render_text_input(
                        'shipping_postcode', 
                        $this->wp->getUserMeta($user->ID, 'shipping_postcode', true), 
                        ['id' => 'shipping_postcode', 'class' => 'regular-text']
                    ); 
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_custom_fields($user_id): void {
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
                $this->wp->updateUserMeta($user_id, $key, sanitize_text_field($_POST[$key]));
            }
        }

        $marketing_consent = isset($_POST['marketing_consent']) ? 1 : 0;
        $this->wp->updateUserMeta($user_id, 'marketing_consent', $marketing_consent);
    }
}