<?php
namespace CannaRewards\Admin;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles the custom metabox for CannaRewards product settings.
 */
class ProductMetabox {

    public static function init() {
        add_action('add_meta_boxes', [self::class, 'add_metabox']);
        add_action('save_post_product', [self::class, 'save_metabox_data']);
    }

    public static function add_metabox() {
        add_meta_box(
            'canna_product_settings_metabox',
            'CannaRewards Product Settings',
            [self::class, 'render_metabox_html'],
            'product',
            'normal',
            'high'
        );
    }

    public static function render_metabox_html($post) {
        wp_nonce_field('canna_product_settings_save', 'canna_product_settings_nonce');

        $points_award = get_post_meta($post->ID, 'points_award', true);
        $points_cost = get_post_meta($post->ID, 'points_cost', true);
        $required_rank_slug = get_post_meta($post->ID, '_required_rank', true);
        $marketing_snippet = get_post_meta($post->ID, 'marketing_snippet', true);

        $ranks = get_posts([
            'post_type' => 'canna_rank',
            'posts_per_page' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => 'points_required',
            'order' => 'ASC',
        ]);
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="canna_points_award">Points Awarded (on scan)</label></th>
                    <td>
                        <input type="number" id="canna_points_award" name="canna_points_award" value="<?php echo esc_attr($points_award); ?>" class="short" />
                        <p class="description">Enter the number of base points a user receives for scanning this product's QR code.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="canna_points_cost">Points Cost (for redemption)</label></th>
                    <td>
                        <input type="number" id="canna_points_cost" name="canna_points_cost" value="<?php echo esc_attr($points_cost); ?>" class="short" />
                        <p class="description">Enter the number of points required to redeem this item. Leave blank if this product cannot be redeemed.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="canna_required_rank">Required Rank (for redemption)</label></th>
                    <td>
                        <select id="canna_required_rank" name="canna_required_rank">
                            <option value="">— No Rank Required —</option>
                            <?php foreach ($ranks as $rank) : ?>
                                <option value="<?php echo esc_attr($rank->post_name); ?>" <?php selected($required_rank_slug, $rank->post_name); ?>>
                                    <?php echo esc_html($rank->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select the minimum rank a user must have to see and redeem this reward.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="canna_marketing_snippet">Marketing Snippet</label></th>
                    <td>
                        <textarea id="canna_marketing_snippet" name="canna_marketing_snippet" rows="3" class="large-text"><?php echo esc_textarea($marketing_snippet); ?></textarea>
                        <p class="description">A short, pre-approved marketing line for this product. This is sent to Customer.io on scan events.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public static function save_metabox_data($post_id) {
        if (!isset($_POST['canna_product_settings_nonce']) || !wp_verify_nonce($_POST['canna_product_settings_nonce'], 'canna_product_settings_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields_to_save = [
            'canna_points_award' => 'points_award',
            'canna_points_cost' => 'points_cost',
            'canna_required_rank' => '_required_rank',
            'canna_marketing_snippet' => 'marketing_snippet',
        ];

        foreach ($fields_to_save as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                $value = sanitize_text_field(wp_unslash($_POST[$post_key]));
                update_post_meta($post_id, $meta_key, $value);
            }
        }
    }
}