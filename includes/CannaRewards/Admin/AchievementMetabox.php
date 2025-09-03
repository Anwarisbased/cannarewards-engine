<?php
namespace CannaRewards\Admin;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Defines the metabox for the CannaRewards Achievement Custom Post Type.
 */
class AchievementMetabox {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_achievement_metabox']);
        add_action('save_post_canna_achievement', [$this, 'save_metabox_data']);
    }

    public function add_achievement_metabox() {
        add_meta_box(
            'canna_achievement_details',
            __('Achievement Details & Rules', 'canna-rewards'),
            [$this, 'render_metabox'],
            'canna_achievement',
            'normal',
            'high'
        );
    }

    public function render_metabox($post) {
        wp_nonce_field('canna_save_achievement_metabox_data', 'canna_achievement_metabox_nonce');

        $achievement_key = get_post_meta($post->ID, 'achievement_key', true);
        $points_reward   = get_post_meta($post->ID, 'points_reward', true);
        $rarity          = get_post_meta($post->ID, 'rarity', true);
        $icon_url        = get_post_meta($post->ID, 'icon_url', true);
        $is_active       = get_post_meta($post->ID, 'is_active', true);
        $trigger_event   = get_post_meta($post->ID, 'trigger_event', true);
        $trigger_count   = get_post_meta($post->ID, 'trigger_count', true);
        $conditions      = get_post_meta($post->ID, 'conditions', true);

        if ($is_active === '') { $is_active = 1; }
        if (empty($trigger_count)) { $trigger_count = 1; }

        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="achievement_key"><?php _e('Achievement Key', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="text" id="achievement_key" name="achievement_key" value="<?php echo esc_attr($achievement_key); ?>" class="regular-text" required />
                        <p class="description"><?php _e('A unique, machine-readable key. Cannot be changed after creation.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="points_reward"><?php _e('Points Reward', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="number" id="points_reward" name="points_reward" value="<?php echo esc_attr($points_reward); ?>" class="small-text" min="0" />
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
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="icon_url"><?php _e('Icon URL', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="url" id="icon_url" name="icon_url" value="<?php echo esc_attr($icon_url); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="is_active"><?php _e('Is Active?', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php checked($is_active, 1); ?> />
                    </td>
                </tr>
                <tr style="border-top: 1px solid #ddd;">
                    <th scope="row" colspan="2"><h4><?php _e('Rule Engine', 'canna-rewards'); ?></h4></th>
                </tr>
                <tr>
                    <th scope="row"><label for="trigger_event"><?php _e('Trigger Event', 'canna-rewards'); ?></label></th>
                    <td>
                        <select id="trigger_event" name="trigger_event">
                            <option value="">-- Select Event --</option>
                            <option value="product_scanned" <?php selected($trigger_event, 'product_scanned'); ?>><?php _e('Product Scanned', 'canna-rewards'); ?></option>
                            <option value="reward_redeemed" <?php selected($trigger_event, 'reward_redeemed'); ?>><?php _e('Reward Redeemed', 'canna-rewards'); ?></option>
                            <option value="user_rank_changed" <?php selected($trigger_event, 'user_rank_changed'); ?>><?php _e('User Rank Changed', 'canna-rewards'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="trigger_count"><?php _e('Trigger Count', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="number" id="trigger_count" name="trigger_count" value="<?php echo esc_attr($trigger_count); ?>" class="small-text" min="1" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="conditions"><?php _e('Conditions (JSON)', 'canna-rewards'); ?></label></th>
                    <td>
                        <textarea id="conditions" name="conditions" rows="5" class="large-text" placeholder='[{"field": "product_snapshot.taxonomy.strain_type", "operator": "is", "value": "Sativa"}]'><?php echo esc_textarea($conditions); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function save_metabox_data($post_id) {
        if (!isset($_POST['canna_achievement_metabox_nonce']) || !wp_verify_nonce($_POST['canna_achievement_metabox_nonce'], 'canna_save_achievement_metabox_data')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (get_post_type($post_id) !== 'canna_achievement' || !current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = [
            'achievement_key' => 'sanitize_text_field',
            'points_reward'   => 'absint',
            'rarity'          => 'sanitize_text_field',
            'icon_url'        => 'esc_url_raw',
            'trigger_event'   => 'sanitize_text_field',
            'trigger_count'   => 'absint',
        ];

        foreach ($fields as $key => $sanitizer) {
            if (isset($_POST[$key])) {
                update_post_meta($post_id, $key, call_user_func($sanitizer, $_POST[$key]));
            }
        }

        $is_active = isset($_POST['is_active']) ? 1 : 0;
        update_post_meta($post_id, 'is_active', $is_active);

        if (isset($_POST['conditions'])) {
            $conditions_json = wp_unslash($_POST['conditions']);
            if (empty(trim($conditions_json)) || json_decode($conditions_json) !== null) {
                update_post_meta($post_id, 'conditions', $conditions_json);
            }
        }
    }
}