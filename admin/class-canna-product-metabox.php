<?php
/**
 * Handles the custom metabox for CannaRewards product settings.
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_Product_Metabox {

    /**
     * Initializes the class by adding the necessary hooks.
     */
    public static function init() {
        add_action('add_meta_boxes', [self::class, 'add_metabox']);
        add_action('save_post_product', [self::class, 'save_metabox_data']);
    }

    /**
     * Adds the metabox to the 'product' post type edit screen.
     */
    public static function add_metabox() {
        add_meta_box(
            'canna_product_settings_metabox',           // ID
            'CannaRewards Product Settings',            // Title
            [self::class, 'render_metabox_html'],       // Callback function
            'product',                                  // Post type
            'normal',                                   // Context
            'high'                                      // Priority
        );
    }

    /**
     * Renders the HTML content for the metabox.
     *
     * @param WP_Post $post The current post object.
     */
    public static function render_metabox_html($post) {
        // Add a nonce field for security
        wp_nonce_field('canna_product_settings_save', 'canna_product_settings_nonce');

        // Get existing values
        $points_cost = get_post_meta($post->ID, 'points_cost', true);
        $required_rank_slug = get_post_meta($post->ID, '_required_rank', true);
        $marketing_snippet = get_post_meta($post->ID, 'marketing_snippet', true);

        // Get all available ranks for the dropdown
        $ranks = get_posts([
            'post_type' => 'canna_rank',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <table class="form-table">
            <tbody>
                <!-- Points Cost Field -->
                <tr>
                    <th><label for="canna_points_cost">Points Cost (for redemption)</label></th>
                    <td>
                        <input type="number" id="canna_points_cost" name="canna_points_cost" value="<?php echo esc_attr($points_cost); ?>" class="short" />
                        <p class="description">Enter the number of points required to redeem this item. Leave blank if this is a scannable (cannabis) product.</p>
                    </td>
                </tr>

                <!-- Required Rank Field -->
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

                <!-- Marketing Snippet Field -->
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

    /**
     * Saves the custom meta data when a product is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public static function save_metabox_data($post_id) {
        // Check if our nonce is set.
        if (!isset($_POST['canna_product_settings_nonce'])) {
            return;
        }
        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['canna_product_settings_nonce'], 'canna_product_settings_save')) {
            return;
        }
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // --- Save our data ---

        // Points Cost
        if (isset($_POST['canna_points_cost'])) {
            $points_val = sanitize_text_field($_POST['canna_points_cost']);
            update_post_meta($post_id, 'points_cost', $points_val === '' ? '' : absint($points_val));
        }

        // Required Rank
        if (isset($_POST['canna_required_rank'])) {
            $rank_val = sanitize_key($_POST['canna_required_rank']);
            update_post_meta($post_id, '_required_rank', $rank_val);
        }

        // Marketing Snippet
        if (isset($_POST['canna_marketing_snippet'])) {
            $snippet_val = sanitize_textarea_field($_POST['canna_marketing_snippet']);
            update_post_meta($post_id, 'marketing_snippet', $snippet_val);
        }
    }
}