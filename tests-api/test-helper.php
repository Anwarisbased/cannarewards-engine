<?php
/**
 * A helper script for Playwright tests to manipulate the database state.
 */

require_once dirname(__DIR__, 4) . '/wp-load.php';
header('Content-Type: application/json');
$action = $_POST['action'] ?? '';
global $wpdb;

switch ($action) {

    /**
     * --- NEW ACTION ---
     * Directly queries for canna_rank posts to debug timing issues.
     */
    case 'debug_get_ranks':
        $args = [
            'post_type'      => 'canna_rank',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];
        $rank_posts = new WP_Query($args);
        
        // We return the raw post objects found.
        echo json_encode(['success' => true, 'ranks_found' => $rank_posts->posts]);
        break;

    case 'clear_rank_cache':
        delete_transient('canna_rank_structure_dtos');
        echo json_encode(['success' => true, 'message' => 'Rank structure cache has been cleared.']);
        break;

    case 'reset_qr_code':
        $code = sanitize_text_field($_POST['code'] ?? '');
        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Code parameter is missing.']);
            exit;
        }
        $wpdb->delete($wpdb->prefix . 'canna_reward_codes', ['code' => $code]);

        // Use SKU that corresponds to product ID 202
        $wpdb->insert($wpdb->prefix . 'canna_reward_codes', [
            'code' => $code,
            'sku'  => 'PWT-001',  // Use SKU that maps to product ID 202
        ]);
        echo json_encode(['success' => true, 'message' => "Code {$code} has been reset with SKU PWT-001."]);
        break;

    case 'prepare_test_product':
        // Ensure the test product with SKU PWT-001 (which should be product ID 202) has 400 points
        if (!class_exists('WooCommerce')) {
            echo json_encode(['success' => false, 'message' => 'WooCommerce is not active.']);
            exit;
        }
        
        // Check if product with SKU PWT-001 exists
        $product_id = wc_get_product_id_by_sku('PWT-001');
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Product with SKU PWT-001 does not exist.']);
            exit;
        }
        
        // Set the points award meta to 400
        update_post_meta($product_id, 'points_award', 400);
        
        echo json_encode(['success' => true, 'message' => "Test product with SKU PWT-001 (ID: {$product_id}) has been prepared with 400 points award."]);
        break;

    case 'simulate_previous_scan':
        // Simulate that the user has already scanned a product before
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email parameter is missing.']);
            exit;
        }
        $user = get_user_by('email', $email);
        if ($user) {
            // Record a fake scan action
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'canna_user_action_log', [
                'user_id' => $user->ID,
                'action_type' => 'scan',
                'created_at' => current_time('mysql', 1)
            ]);
            echo json_encode(['success' => true, 'message' => "Simulated previous scan for user."]);
        } else {
            echo json_encode(['success' => false, 'message' => "User not found."]);
        }
        break;

    case 'reset_user_by_email':
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email parameter is missing.']);
            exit;
        }
        $user = get_user_by('email', $email);
        if ($user) {
            if (class_exists('WooCommerce')) {
                $orders = wc_get_orders(['customer' => $email]);
                foreach ($orders as $order) { $order->delete(true); }
            }
            if (isset($_POST['points_balance'])) {
                update_user_meta($user->ID, '_canna_points_balance', absint($_POST['points_balance']));
            }
            if (isset($_POST['lifetime_points'])) {
                update_user_meta($user->ID, '_canna_lifetime_points', absint($_POST['lifetime_points']));
            }
            echo json_encode(['success' => true, 'message' => "User {$email} has been reset."]);
        } else {
            echo json_encode(['success' => true, 'message' => "User {$email} not found, proceeding."]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing action parameter.']);
        break;
}

exit;