<?php
namespace CannaRewards\Repositories;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Order Repository
 *
 * Handles data access related to WooCommerce orders.
 */
class OrderRepository {
    
    public function createFromRedemption(int $user_id, int $product_id, array $shipping_details = []): ?int {
        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }

        $order = wc_create_order(['customer_id' => $user_id]);
        if (is_wp_error($order)) {
            return null;
        }

        $order->add_product($product, 1);
        
        if (!empty($shipping_details)) {
            $order->set_address($shipping_details, 'shipping');
            $order->set_address($shipping_details, 'billing');
        }
        
        $order->calculate_totals();
        $order->update_meta_data('_is_canna_redemption', true);
        $order->update_status('processing', 'Redeemed with CannaRewards points.');
        
        $order->save();

        return $order->get_id();
    }

    public function getUserOrders(int $user_id, int $limit = 50): array {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'meta_key'    => '_is_canna_redemption',
            'meta_value'  => true,
        ]);

        $formatted_orders = [];
        foreach ($orders as $order) {
            $items = [];
            $image_url = wc_placeholder_img_src();
            $line_items = $order->get_items();
            
            if (!empty($line_items)) {
                $first_item = reset($line_items);
                $product_id = $first_item->get_product_id();
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    $image_id = $product ? $product->get_image_id() : 0;
                    if ($image_id) {
                        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                    }
                }
            }
            
            foreach ($line_items as $item) {
                $items[] = $item->get_name();
            }

            $formatted_orders[] = [
                'orderId'   => $order->get_id(),
                'date'      => $order->get_date_created()->date('F j, Y'),
                'status'    => ucfirst($order->get_status()),
                'items'     => implode(', ', $items),
                'imageUrl'  => $image_url,
            ];
        }

        return $formatted_orders;
    }
}