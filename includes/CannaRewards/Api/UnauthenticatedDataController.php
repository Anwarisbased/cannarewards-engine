<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Unauthenticated Data Controller
 * Provides public endpoints for data needed before a user is logged in.
 * This class is intentionally self-contained and does not use the DI container
 * to ensure it remains operational even during major refactoring.
 */
class UnauthenticatedDataController {

    /**
     * Formats a product for a simple, public API response.
     */
    private function format_product_preview( $product_id ): ?array {
        if ( ! $product_id || ! function_exists('wc_get_product') ) {
            return null;
        }
        $product = wc_get_product($product_id);
        if ( ! $product ) {
            return null;
        }

        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src();

        return [
            'id'    => $product->get_id(),
            'name'  => $product->get_name(),
            'image' => $image_url,
        ];
    }

    /**
     * Gets the preview data for the first-scan welcome reward.
     */
    public function get_welcome_reward_preview( WP_REST_Request $request ): WP_REST_Response {
        $options = get_option('canna_rewards_options', []);
        $product_id = !empty($options['welcome_reward_product']) ? (int) $options['welcome_reward_product'] : 0;
        
        if ($product_id === 0) {
            $error = new WP_Error('not_configured', 'The welcome reward has not been configured in Brand Settings.', ['status' => 404]);
            return rest_ensure_response($error);
        }
        
        $preview_data = $this->format_product_preview($product_id);
        
        if ( is_null($preview_data) ) {
            $error = new WP_Error('not_found', 'Welcome reward product could not be found.', ['status' => 404]);
            return rest_ensure_response($error);
        }

        return new WP_REST_Response($preview_data, 200);
    }

    /**
     * Gets the preview data for the referral sign-up gift.
     */
    public function get_referral_gift_preview( WP_REST_Request $request ): WP_REST_Response {
        $options = get_option('canna_rewards_options', []);
        $product_id = !empty($options['referral_signup_gift']) ? (int) $options['referral_signup_gift'] : 0;

        if ($product_id === 0) {
            $error = new WP_Error('not_configured', 'The referral gift has not been configured in Brand Settings.', ['status' => 404]);
            return rest_ensure_response($error);
        }

        $preview_data = $this->format_product_preview($product_id);

        if ( is_null($preview_data) ) {
            $error = new WP_Error('not_found', 'Referral gift product could not be found.', ['status' => 404]);
            return rest_ensure_response($error);
        }
        
        $preview_data['isReferralGift'] = true;
        
        return new WP_REST_Response($preview_data, 200);
    }
}