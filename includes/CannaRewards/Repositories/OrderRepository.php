<?php
namespace CannaRewards\Repositories;

use CannaRewards\DTO\OrderDTO;
use CannaRewards\Infrastructure\WordPressApiWrapper;
use Exception;
use WC_Order_Item_Product;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Order Repository
 */
class OrderRepository {
    private WordPressApiWrapper $wp;

    public function __construct(WordPressApiWrapper $wp) {
        $this->wp = $wp;
    }
    
    public function createFromRedemption(int $user_id, int $product_id, array $shipping_details = []): ?int {
        $product = $this->wp->getProduct($product_id);
        if (!$product) {
            throw new Exception("Could not find product with ID {$product_id} for redemption.");
        }
        
        try {
            $order = $this->wp->createOrder(['customer_id' => $user_id]);
            if ($order instanceof \WP_Error) {
                throw new Exception('wc_create_order() failed. WooCommerce said: ' . $order->get_error_message());
            }

            if (!empty($shipping_details)) {
                $order->set_address($shipping_details, 'shipping');
                $order->set_address($shipping_details, 'billing');
            }
            
            $order->add_product($product, 1);
            $order->set_total(0);
            $order->update_meta_data('_is_canna_redemption', true);
            $order->update_status('processing', 'Redeemed with CannaRewards points.');
            
            $order_id = $order->save();
            if ($order_id === 0) {
                 throw new Exception('$order->save() returned 0, indicating a silent failure.');
            }

            return $order_id;

        } catch (Exception $e) {
            throw new Exception('Exception during order creation process: ' . $e->getMessage());
        }
    }

    /**
     * @return OrderDTO[]
     */
    public function getUserOrders(int $user_id, int $limit = 50): array {
        // <<<--- REFACTOR: Use the wrapper
        $orders = $this->wp->getOrders([
            'customer_id' => $user_id,
            'limit'       => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'meta_key'    => '_is_canna_redemption',
            'meta_value'  => true,
        ]);

        $formatted_orders = [];
        foreach ($orders as $order) {
            $image_url = $this->wp->getPlaceholderImageSrc();
            $line_items = $order->get_items();
            
            $item_names = array_map(fn($item) => $item->get_name(), $line_items);

            if (!empty($line_items)) {
                /** @var WC_Order_Item_Product $first_item */
                $first_item = reset($line_items);
                $product_id = $first_item->get_product_id();
                $product = $product_id ? $this->wp->getProduct($product_id) : null;
                $image_id = $product ? $product->get_image_id() : 0;
                if ($image_id) {
                    $image_url = $this->wp->getAttachmentImageUrl($image_id, 'thumbnail');
                }
            }

            $dto = new OrderDTO(
                orderId: \CannaRewards\Domain\ValueObjects\OrderId::fromInt($order->get_id()),
                date: $order->get_date_created()->date('Y-m-d'),
                status: ucfirst($order->get_status()),
                items: implode(', ', $item_names),
                imageUrl: $image_url
            );

            $formatted_orders[] = $dto;
        }

        return $formatted_orders;
    }
}