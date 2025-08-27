<?php
/**
 * Defines the metabox for the CannaRewards Achievement Custom Post Type.
 *
 * This class handles the creation and saving of custom fields for achievements,
 * including type, points reward, rarity, icon URL, and active status.
 *
 * @package CannaRewards
 * @subpackage Admin
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

class Canna_Achievement_Metabox {

    /**
     * Constructor.
     * Registers hooks for adding the metabox and saving its data.
     */
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_achievement_metabox']);
        add_action('save_post_canna_achievement', [$this, 'save_achievement_metabox_data']);
    }

    /**
     * Adds the custom metabox to the Achievement CPT edit screen.
     */
    public function add_achievement_metabox() {
        add_meta_box(
            'canna_achievement_details',
            __('Achievement Details', 'canna-rewards'),
            [$this, 'render_achievement_metabox'],
            'canna_achievement',
            'normal',
            'high'
        );
    }

    /**
     * Renders the HTML for the achievement details metabox.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_achievement_metabox($post) {
        // Add a nonce field so we can check it later.
        wp_nonce_field('canna_save_achievement_metabox_data', 'canna_achievement_metabox_nonce');

        // Retrieve existing meta values
        $achievement_key = get_post_meta($post->ID, 'achievement_key', true);
        $type            = get_post_meta($post->ID, 'type', true);
        $points_reward   = get_post_meta($post->ID, 'points_reward', true);
        $rarity          = get_post_meta($post->ID, 'rarity', true);
        $icon_url        = get_post_meta($post->ID, 'icon_url', true);
        $is_active       = get_post_meta($post->ID, 'is_active', true);

        // Set default values if not already set
        if (empty($type)) {
            $type = 'general';
        }
        if (empty($points_reward)) {
            $points_reward = 0;
        }
        if (empty($rarity)) {
            $rarity = 'common';
        }
        if ($is_active === '') { // Check for strict empty string as 0 is a valid value
            $is_active = 1;
        }

        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="achievement_key"><?php _e('Achievement Key', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="text" id="achievement_key" name="achievement_key" value="<?php echo esc_attr($achievement_key); ?>" class="regular-text" placeholder="<?php _e('Unique identifier (e.g., first_scan)', 'canna-rewards'); ?>" required />
                        <p class="description"><?php _e('A unique, machine-readable key for this achievement. Cannot be changed after creation.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="type"><?php _e('Type', 'canna-rewards'); ?></label></th>
                    <td>
                        <select id="type" name="type">
                            <option value="general" <?php selected($type, 'general'); ?>><?php _e('General', 'canna-rewards'); ?></option>
                            <option value="scan" <?php selected($type, 'scan'); ?>><?php _e('Scan Related', 'canna-rewards'); ?></option>
                            <option value="redeem" <?php selected($type, 'redeem'); ?>><?php _e('Redeem Related', 'canna-rewards'); ?></option>
                            <option value="profile" <?php selected($type, 'profile'); ?>><?php _e('Profile Related', 'canna-rewards'); ?></option>
                            <option value="onboarding" <?php selected($type, 'onboarding'); ?>><?php _e('Onboarding', 'canna-rewards'); ?></option>
                        </select>
                        <p class="description"><?php _e('Categorizes the achievement for filtering and logic.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="points_reward"><?php _e('Points Reward', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="number" id="points_reward" name="points_reward" value="<?php echo esc_attr($points_reward); ?>" class="small-text" min="0" />
                        <p class="description"><?php _e('Points awarded to the user upon unlocking this achievement.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rarity"><?php _e('Rarity', 'canna-rewards'); ?></label></th>
                    <td>
                        <select id="rarity" name="rarity">
                            <option value="common" <?php selected($rarity, 'common'); ?>><?php _e('Common', 'canna-rewards'); ?></option>
                            <option value="uncommon" <?php selected($rarity, 'uncommon'); ?>><?php _e('Uncommon', 'canna-rewards'); ?></option>
                            <option value="rare" <?php selected($rarity, 'rare'); ?>><?php _e('Rare', 'canna-rewards'); ?></option>
                            <option value="epic" <?php selected($rarity, 'epic'); ?>><?php _e('Epic', 'canna-rewards'); ?></option>
                            <option value="legendary" <?php selected($rarity, 'legendary'); ?>><?php _e('Legendary', 'canna-rewards'); ?></option>
                        </select>
                        <p class="description"><?php _e('How difficult or special this achievement is.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="icon_url"><?php _e('Icon URL', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="url" id="icon_url" name="icon_url" value="<?php echo esc_attr($icon_url); ?>" class="regular-text" placeholder="<?php _e('URL to an icon image for this achievement', 'canna-rewards'); ?>" />
                        <p class="description"><?php _e('Enter the full URL to an image that represents this achievement.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="is_active"><?php _e('Is Active?', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php checked($is_active, 1); ?> />
                        <p class="description"><?php _e('Uncheck to temporarily disable this achievement from being awarded.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Saves the custom metabox data when an Achievement CPT is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_achievement_metabox_data($post_id) {
        // Check if our nonce is set.
        if (!isset($_POST['canna_achievement_metabox_nonce'])) {
            return $post_id;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['canna_achievement_metabox_nonce'], 'canna_save_achievement_metabox_data')) {
            return $post_id;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        // Sanitize and save the custom fields.
        $fields_to_save = [
            'achievement_key' => 'sanitize_text_field',
            'type'            => 'sanitize_text_field',
            'points_reward'   => 'absint', // Absolute integer
            'rarity'          => 'sanitize_text_field',
            'icon_url'        => 'esc_url_raw',
            'is_active'       => 'absint', // 0 or 1
        ];

        foreach ($fields_to_save as $field => $sanitizer) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitizer, $_POST[$field]);
                update_post_meta($post_id, $field, $value);
            } else {
                // For checkboxes, if not set, it means it's unchecked (value 0).
                if ($field === 'is_active') {
                    update_post_meta($post_id, $field, 0);
                }
            }
        }
    }
}