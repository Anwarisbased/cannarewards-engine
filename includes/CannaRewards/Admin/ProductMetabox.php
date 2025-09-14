<?php
namespace CannaRewards\Admin;

use CannaRewards\Domain\MetaKeys;
use CannaRewards\Infrastructure\WordPressApiWrapper;

// ARCHITECTURAL NOTE: This class exists within the Admin boundary.
// Direct calls to WordPress functions (e.g., get_post_meta, add_meta_box)
// are permitted here for pragmatic integration with the WordPress admin UI.
// This contrasts with the core application logic in Services/Repositories,
// which must remain pure.

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles the custom metabox for CannaRewards product settings.
 */
final class ProductMetabox {
    private WordPressApiWrapper $wp;
    private FieldFactory $fieldFactory;

    public function __construct(WordPressApiWrapper $wp, FieldFactory $fieldFactory) {
        $this->wp = $wp;
        $this->fieldFactory = $fieldFactory;
    }

    public function init(): void {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_product', [$this, 'save_metabox_data']);
    }

    public function add_metabox(): void {
        add_meta_box(
            'canna_product_settings_metabox',
            'CannaRewards Product Settings',
            [$this, 'render_metabox_html'],
            'product',
            'normal',
            'high'
        );
    }

    public function render_metabox_html($post): void {
        wp_nonce_field('canna_product_settings_save', 'canna_product_settings_nonce');

        $points_award = $this->wp->getPostMeta($post->ID, MetaKeys::POINTS_AWARD, true);
        $points_cost = $this->wp->getPostMeta($post->ID, MetaKeys::POINTS_COST, true);
        $required_rank_slug = $this->wp->getPostMeta($post->ID, MetaKeys::REQUIRED_RANK, true);
        $marketing_snippet = $this->wp->getPostMeta($post->ID, 'marketing_snippet', true);

        $ranks = $this->wp->getPosts([
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
                        <?php 
                        $this->fieldFactory->render_text_input(
                            'canna_points_award', 
                            $points_award, 
                            ['id' => 'canna_points_award', 'type' => 'number', 'class' => 'short', 'description' => 'Enter the number of base points a user receives for scanning this product\'s QR code.']
                        ); 
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="canna_points_cost">Points Cost (for redemption)</label></th>
                    <td>
                        <?php 
                        $this->fieldFactory->render_text_input(
                            'canna_points_cost', 
                            $points_cost, 
                            ['id' => 'canna_points_cost', 'type' => 'number', 'class' => 'short', 'description' => 'Enter the number of points required to redeem this item. Leave blank if this product cannot be redeemed.']
                        ); 
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="canna_required_rank">Required Rank (for redemption)</label></th>
                    <td>
                        <?php
                        $rank_options = ['' => '— No Rank Required —'];
                        foreach ($ranks as $rank) {
                            $rank_options[$rank->post_name] = $rank->post_title;
                        }
                        
                        $this->fieldFactory->render_select(
                            'canna_required_rank',
                            $required_rank_slug,
                            $rank_options,
                            ['id' => 'canna_required_rank', 'description' => 'Select the minimum rank a user must have to see and redeem this reward.']
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="canna_marketing_snippet">Marketing Snippet</label></th>
                    <td>
                        <?php 
                        $this->fieldFactory->render_textarea(
                            'canna_marketing_snippet', 
                            $marketing_snippet, 
                            ['id' => 'canna_marketing_snippet', 'rows' => '3', 'class' => 'large-text', 'description' => 'A short, pre-approved marketing line for this product. This is sent to Customer.io on scan events.']
                        ); 
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function save_metabox_data($post_id): void {
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
            'canna_points_award' => MetaKeys::POINTS_AWARD,
            'canna_points_cost' => MetaKeys::POINTS_COST,
            'canna_required_rank' => MetaKeys::REQUIRED_RANK,
            'canna_marketing_snippet' => 'marketing_snippet',
        ];

        foreach ($fields_to_save as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                $value = sanitize_text_field(wp_unslash($_POST[$post_key]));
                $this->wp->updatePostMeta($post_id, $meta_key, $value);
            }
        }
    }
}