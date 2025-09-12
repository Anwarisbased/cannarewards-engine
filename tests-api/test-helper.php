<?php
/**
 * A helper script for Playwright tests to manipulate the database state.
 *
 * IMPORTANT: This file is for local development and testing ONLY.
 * It MUST be excluded from all production deployments via .gitignore.
 */

require_once dirname(__DIR__, 4) . '/wp-load.php';

// A simple check to prevent accidental production execution if .gitignore fails.
if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This script cannot be run in a production environment.']);
    exit;
}

header('Content-Type: application/json');
$action = $_POST['action'] ?? '';
global $wpdb;

switch ($action) {

    case 'delete_user_by_email':
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email parameter is missing.']);
            exit;
        }
        
        // This file is required for wp_delete_user()
        require_once(ABSPATH.'wp-admin/includes/user.php');
        $user = get_user_by('email', $email);

        if ($user) {
            wp_delete_user($user->ID);
            echo json_encode(['success' => true, 'message' => "User {$email} deleted."]);
        } else {
            echo json_encode(['success' => true, 'message' => "User {$email} not found, nothing to delete."]);
        }
        break;

    case 'debug_get_ranks':
        $args = [
            'post_type'      => 'canna_rank',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];
        $rank_posts = new WP_Query($args);
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
        $wpdb->insert($wpdb->prefix . 'canna_reward_codes', [
            'code' => $code,
            'sku'  => 'PWT-001',
        ]);
        echo json_encode(['success' => true, 'message' => "Code {$code} has been reset with SKU PWT-001."]);
        break;

    case 'prepare_test_product':
        if (!class_exists('WooCommerce')) {
            echo json_encode(['success' => false, 'message' => 'WooCommerce is not active.']);
            exit;
        }
        $product_id = wc_get_product_id_by_sku('PWT-001');
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Product with SKU PWT-001 does not exist.']);
            exit;
        }
        update_post_meta($product_id, 'points_award', 400);
        echo json_encode(['success' => true, 'message' => "Test product with SKU PWT-001 (ID: {$product_id}) has been prepared with 400 points award."]);
        break;

    case 'simulate_previous_scan':
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email parameter is missing.']);
            exit;
        }
        $user = get_user_by('email', $email);
        if ($user) {
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