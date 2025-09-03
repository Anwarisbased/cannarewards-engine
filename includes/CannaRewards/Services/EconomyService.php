<?php
namespace CannaRewards\Services;

use Exception;
use CannaRewards\Includes\Event;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Economy Service
 *
 * The central "bank" for the platform. This is the ONLY class allowed to modify
 * a user's points balance or grant products. It ensures all transactions
 * are logged consistently.
 */
class EconomyService {
    private $cdp_service;
    private $action_log_service;
    private $context_builder;

    public function __construct() {
        $this->cdp_service        = new CDPService();
        $this->action_log_service = new ActionLogService();
        $this->context_builder    = new ContextBuilderService();
    }

    /**
     * The single entry point for processing a product scan from a QR code.
     * This orchestrates the entire scan flow and broadcasts the 'product_scanned' event.
     *
     * @param int    $user_id       The ID of the user scanning.
     * @param string $code_to_claim The unique code from the QR.
     * @return array The result of the transaction for the API response.
     * @throws Exception If the code is invalid, used, or the product doesn't exist.
     */
    public function process_scan( int $user_id, string $code_to_claim ): array {
        global $wpdb;

        $codes_table = $wpdb->prefix . 'canna_reward_codes';
        $code_data   = $wpdb->get_row( $wpdb->prepare( "SELECT id, sku FROM {$codes_table} WHERE code = %s AND is_used = 0", $code_to_claim ) );

        if ( ! $code_data ) {
            throw new Exception( 'This code is invalid or has already been used.' );
        }

        $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1", $code_data->sku ) );
        if ( ! $product_id ) {
            throw new Exception( 'The product associated with this code could not be found.' );
        }
        $product_post = get_post( $product_id );
        $base_points  = (int) get_post_meta( $product_id, 'points_award', true );
        $product_name = $product_post->post_title;

        $description   = 'Product Scan: ' . $product_name;
        $points_result = $this->grant_points( $user_id, $base_points, $description );
        
        $wpdb->update(
            $codes_table,
            [ 'is_used' => 1, 'user_id' => $user_id, 'claimed_at' => current_time( 'mysql', 1 ) ],
            [ 'id' => $code_data->id ]
        );
        
        $full_context = $this->context_builder->build_event_context($user_id, $product_post);
        Event::broadcast('product_scanned', $full_context);

        return [
            'success'            => true,
            'message'            => sprintf( 'You earned %d points for scanning %s!', $points_result['points_earned'], $product_name ),
            'points_earned'      => $points_result['points_earned'],
            'new_points_balance' => $points_result['new_points_balance'],
        ];
    }

    /**
     * Grants points to a user, applying rank multipliers and logging the transaction.
     */
    public function grant_points( int $user_id, int $base_points, string $description, float $temp_multiplier = 1.0 ): array {
        $user_rank        = get_user_current_rank( $user_id );
        $rank_multiplier  = (float) ( $user_rank['multiplier'] ?? 1.0 );
        $final_multiplier = max( $rank_multiplier, $temp_multiplier );
        $points_to_grant  = floor( $base_points * $final_multiplier );

        $current_balance     = get_user_points_balance( $user_id );
        $new_balance         = $current_balance + $points_to_grant;
        update_user_meta( $user_id, '_canna_points_balance', $new_balance );

        $lifetime_points     = get_user_lifetime_points( $user_id );
        $new_lifetime_points = $lifetime_points + $points_to_grant;
        update_user_meta( $user_id, '_canna_lifetime_points', $new_lifetime_points );

        $log_meta_data = [
            'description'        => $description,
            'points_change'      => $points_to_grant,
            'new_balance'        => $new_balance,
            'base_points'        => $base_points,
            'multiplier_applied' => $final_multiplier > 1.0 ? $final_multiplier : null,
        ];
        $this->action_log_service->record( $user_id, 'points_granted', 0, $log_meta_data );

        $this->cdp_service->track(
            $user_id,
            'user_points_balance_changed',
            [ 'change_amount' => $points_to_grant, 'reason' => $description ]
        );

        $this->check_and_apply_rank_transition( $user_id );

        return [
            'points_earned'      => $points_to_grant,
            'new_points_balance' => $new_balance,
            'base_points'        => $base_points,
            'multiplier_applied' => $final_multiplier,
        ];
    }

    /**
     * Redeems points for a product, creating an order and logging the transaction.
     */
    public function redeem_points( int $user_id, int $product_id, array $shipping_details = [] ) {
        if ( ! function_exists('wc_get_product') ) {
            throw new Exception( 'WooCommerce is not active or functions are not loaded.' );
        }
        $points_cost     = (int) get_post_meta( $product_id, 'points_cost', true );
        $current_balance = get_user_points_balance( $user_id );
        if ( $current_balance < $points_cost ) {
            throw new Exception( 'Insufficient points.' );
        }
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            throw new Exception( 'Invalid product for redemption.' );
        }
        $order = wc_create_order( [ 'customer_id' => $user_id ] );
        $order->add_product( $product, 1 );
        if ( ! empty( $shipping_details ) ) {
            $order->set_address( $shipping_details, 'shipping' );
            $order->set_address( $shipping_details, 'billing' );
        }
        $order->calculate_totals();
        $order->update_status( 'processing', 'Redeemed with CannaRewards points.' );
        $order_id = $order->get_id();
        $new_balance = $current_balance - $points_cost;
        update_user_meta( $user_id, '_canna_points_balance', $new_balance );

        $full_context = $this->context_builder->build_event_context($user_id, get_post($product_id));
        
        $log_meta_data = [
            'description'      => 'Redeemed: ' . $product->get_name(),
            'points_change'    => -$points_cost,
            'new_balance'      => $new_balance,
            'order_id'         => $order_id,
            'product_snapshot' => $full_context['product_snapshot'],
        ];
        $this->action_log_service->record( $user_id, 'redeem', $product_id, $log_meta_data );
        
        $this->cdp_service->track( $user_id, 'user_reward_redeemed', $log_meta_data );

        return ['success' => true, 'order_id' => $order_id, 'new_points_balance' => $new_balance];
    }

    public function get_user_orders( int $user_id, int $limit = 50 ): array {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return [];
        }
        $orders = wc_get_orders(['customer_id' => $user_id, 'limit' => $limit, 'orderby' => 'date', 'order' => 'DESC']);
        $formatted_orders = [];
        if ( empty( $orders ) ) {
            return $formatted_orders;
        }
        foreach ( $orders as $order ) {
            $items_array = [];
            $image_url   = wc_placeholder_img_src();
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                $items_array[] = $item->get_name();
                if ( $product && $product->get_image_id() ) {
                    $image_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
                }
            }
            $formatted_orders[] = [
                'orderId'  => $order->get_id(),
                'date'     => $order->get_date_created()->date( 'F j, Y' ),
                'status'   => wc_get_order_status_name( $order->get_status() ),
                'items'    => implode( ', ', $items_array ),
                'total'    => $order->get_total(),
                'imageUrl' => $image_url,
            ];
        }
        return $formatted_orders;
    }

    /**
     * Checks if a user's new lifetime point total qualifies them for a new rank.
     * If so, it updates their rank and broadcasts the 'user_rank_changed' event.
     *
     * @param int $user_id The user's ID.
     */
    private function check_and_apply_rank_transition( int $user_id ) {
        $current_rank_key = get_user_meta( $user_id, '_canna_current_rank_key', true );
        if ( empty( $current_rank_key ) ) {
            $current_rank_key = 'member'; // Default starting rank
            update_user_meta( $user_id, '_canna_current_rank_key', $current_rank_key );
        }
        
        $new_rank_data = get_user_current_rank( $user_id );
        $new_rank_key = $new_rank_data['key'];

        if ( $new_rank_key !== $current_rank_key ) {
            update_user_meta( $user_id, '_canna_current_rank_key', $new_rank_key );

            $all_ranks = canna_get_rank_structure();
            $old_rank_object = $all_ranks[ $current_rank_key ] ?? null;
            $new_rank_object = $all_ranks[ $new_rank_key ] ?? null;

            $this->action_log_service->record($user_id, 'rank_changed', 0, [
                'old_rank_name' => $old_rank_object['name'] ?? 'N/A',
                'new_rank_name' => $new_rank_object['name'] ?? 'N/A',
            ]);

            Event::broadcast('user_rank_changed', [
                'user_id'  => $user_id,
                'old_rank' => $old_rank_object,
                'new_rank' => $new_rank_object,
            ]);
        }
    }
}