<?php
/**
 * Handles the creation of custom meta boxes for CPTs and Products.
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_Meta_Boxes {

    /**
     * Initializes the class by adding hooks to create, display, and save meta boxes.
     */
    public static function init() {
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
        add_action('save_post', [self::class, 'save_meta_data']);
    }

    /**
     * Registers the meta boxes for the relevant post types.
     */
    public static function add_meta_boxes() {
        // Meta box for Ranks (canna_rank)
        add_meta_box(
            'canna_rank_details',
            'Rank Details',
            [self::class, 'render_rank_meta_box'],
            'canna_rank',
            'normal',
            'high'
        );

        // Meta box for Products (product)
        add_meta_box(
            'canna_product_details',
            'CannaRewards Details',
            [self::class, 'render_product_meta_box'],
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Renders the HTML for the Rank Details meta box.
     * @param WP_Post $post The current post object.
     */
    public static function render_rank_meta_box($post) {
        wp_nonce_field('canna_save_meta_data', 'canna_meta_nonce');

        $point_multiplier = get_post_meta($post->ID, 'point_multiplier', true) ?: '1.0';
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="point_multiplier">Point Multiplier</label></th>
                    <td>
                        <input type="text" id="point_multiplier" name="point_multiplier" value="<?php echo esc_attr($point_multiplier); ?>" class="small-text" />
                        <p class="description">The multiplier for points earned on scans. Example: <code>1.2</code> for a 20% bonus. The default is <code>1.0</code>.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Renders the HTML for the Product Details meta box.
     * @param WP_Post $post The current post object.
     */
    public static function render_product_meta_box($post) {
        wp_nonce_field('canna_save_meta_data', 'canna_meta_nonce');

        $points_value = get_post_meta($post->ID, 'points_value', true);
        $required_rank = get_post_meta($post->ID, '_required_rank', true);

        // Fetch all defined ranks to populate the dropdown
        $ranks = canna_get_rank_structure();
        unset($ranks['member']); // Don't need to set "member" as a requirement
        ?>
        <p>Configure how this product interacts with the CannaRewards system. Leave fields blank if they do not apply.</p>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="points_value">Points Value (for Scannable Products)</label></th>
                    <td>
                        <input type="number" id="points_value" name="points_value" value="<?php echo esc_attr($points_value); ?>" class="small-text" />
                        <p class="description">The base number of points a user earns for scanning this product. <strong>Only for cannabis products.</strong></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="_required_rank">Required Rank (for Redeemable Rewards)</label></th>
                    <td>
                        <select name="_required_rank" id="_required_rank">
                            <option value="">-- No Rank Required --</option>
                            <?php foreach ($ranks as $slug => $rank_data) : ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($required_rank, $slug); ?>>
                                    <?php echo esc_html($rank_data['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">If set, only users of this rank or higher can see and redeem this reward. <strong>Only for merchandise.</strong></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Saves the custom meta data when a post is saved.
     * @param int $post_id The ID of the post being saved.
     */
    public static function save_meta_data($post_id) {
        if (!isset($_POST['canna_meta_nonce']) || !wp_verify_nonce($_POST['canna_meta_nonce'], 'canna_save_meta_data')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save Rank Meta Data
        if (isset($_POST['point_multiplier'])) {
            $multiplier = sanitize_text_field($_POST['point_multiplier']);
            update_post_meta($post_id, 'point_multiplier', $multiplier);
        }

        // Save Product Meta Data
        if (isset($_POST['points_value'])) {
            $points = absint($_POST['points_value']);
            update_post_meta($post_id, 'points_value', $points);
        }
        if (isset($_POST['_required_rank'])) {
            $rank = sanitize_key($_POST['_required_rank']);
            update_post_meta($post_id, '_required_rank', $rank);
        }
    }
}