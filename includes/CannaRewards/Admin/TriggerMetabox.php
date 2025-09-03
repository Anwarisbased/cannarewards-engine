<?php
namespace CannaRewards\Admin;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Defines the metabox for the CannaRewards Trigger CPT.
 *
 * @package CannaRewards
 */
class TriggerMetabox {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_canna_trigger', [$this, 'save_data']);
    }

    public function add_metabox() {
        add_meta_box(
            'canna_trigger_details',
            __('Trigger Rules', 'canna-rewards'),
            [$this, 'render_metabox'],
            'canna_trigger', 
            'normal', 
            'high'
        );
    }

    public function render_metabox($post) {
        wp_nonce_field('canna_save_trigger_data', 'canna_trigger_nonce');
        
        $event_key = get_post_meta($post->ID, 'event_key', true);
        $action_type = get_post_meta($post->ID, 'action_type', true);
        $action_value = get_post_meta($post->ID, 'action_value', true);
        ?>
        <p>Use the trigger title above to give this rule a descriptive name (e.g., "Referrer Conversion Bonus").</p>
        <hr>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="event_key">IF this event happens...</label></th>
                    <td>
                        <select id="event_key" name="event_key" required>
                            <option value="">-- Select Event --</option>
                            <option value="referral_invitee_signed_up" <?php selected($event_key, 'referral_invitee_signed_up'); ?>>Referral Invitee Signs Up</option>
                            <option value="referral_converted" <?php selected($event_key, 'referral_converted'); ?>>Referral is Converted (First Scan)</option>
                            <option value="user_rank_changed" <?php selected($event_key, 'user_rank_changed'); ?>>User Rank Changes</option>
                        </select>
                        <p class="description">This is the event that will cause this action to run.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="action_type">THEN perform this action...</label></th>
                    <td>
                        <select id="action_type" name="action_type" required>
                            <option value="">-- Select Action --</option>
                            <option value="grant_points" <?php selected($action_type, 'grant_points'); ?>>Grant Points</option>
                            <!-- Future actions like 'grant_product' can be added here -->
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="action_value">With this value...</label></th>
                    <td>
                        <input type="text" id="action_value" name="action_value" value="<?php echo esc_attr($action_value); ?>" class="regular-text" />
                        <p class="description">For "Grant Points", this is the number of points (e.g., 500).</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function save_data($post_id) {
        if (!isset($_POST['canna_trigger_nonce']) || !wp_verify_nonce($_POST['canna_trigger_nonce'], 'canna_save_trigger_data')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (get_post_type($post_id) !== 'canna_trigger' || !current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, 'event_key', isset($_POST['event_key']) ? sanitize_text_field($_POST['event_key']) : '');
        update_post_meta($post_id, 'action_type', isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '');
        update_post_meta($post_id, 'action_value', isset($_POST['action_value']) ? sanitize_text_field($_POST['action_value']) : '');
    }
}