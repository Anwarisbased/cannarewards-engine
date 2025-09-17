<?php
/**
 * A helper script for Playwright tests to manipulate the database state.
 *
 * IMPORTANT: This file is for local development and testing ONLY.
 * It MUST be excluded from all production deployments via .gitignore.
 */

// Add database optimization
if (function_exists('wpdb')) {
    // Increase MySQL timeout settings for tests
    global $wpdb;
    $wpdb->query("SET SESSION wait_timeout = 600");
    $wpdb->query("SET SESSION interactive_timeout = 600");
}

require_once dirname(__DIR__, 4) . '/wp-load.php';

// A simple check to prevent accidental production execution if .gitignore fails.
if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This script cannot be run in a production environment.']);
    exit;
}

// Add error logging for debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
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
        
        // Add points required information
        $ranks_with_points = [];
        foreach ($rank_posts->posts as $post) {
            $points_required = get_post_meta($post->ID, 'points_required', true);
            $post->points_required = $points_required;
            $ranks_with_points[] = $post;
        }
        
        echo json_encode(['success' => true, 'ranks_found' => $ranks_with_points]);
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
        update_post_meta($product_id, 'points_cost', 500);
        echo json_encode(['success' => true, 'message' => "Test product with SKU PWT-001 (ID: {$product_id}) has been prepared with 400 points award and 500 points cost.", 'product_id' => $product_id]);
        break;

    case 'get_test_product_id':
        if (!class_exists('WooCommerce')) {
            echo json_encode(['success' => false, 'message' => 'WooCommerce is not active.']);
            exit;
        }
        $product_id = wc_get_product_id_by_sku('PWT-001');
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Product with SKU PWT-001 does not exist.']);
            exit;
        }
        echo json_encode(['success' => true, 'product_id' => $product_id]);
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
            // Clear any rank cache
            delete_user_meta($user->ID, '_canna_current_rank_key');
            echo json_encode(['success' => true, 'message' => "User {$email} has been reset."]);
        } else {
            // User doesn't exist, which is fine for reset operations
            echo json_encode(['success' => true, 'message' => "User {$email} not found, proceeding."]);
        }
        break;

    case 'setup_test_achievement':
        // Delete any existing achievement with the key scan_3_times
        $wpdb->delete($wpdb->prefix . 'canna_achievements', ['achievement_key' => 'scan_3_times']);
        
        // Insert a new test achievement
        $wpdb->insert($wpdb->prefix . 'canna_achievements', [
            'achievement_key' => 'scan_3_times',
            'title' => 'Triple Scanner',
            'trigger_event' => 'product_scanned',
            'trigger_count' => 3,
            'points_reward' => 500,
            'conditions' => '[]'
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Test achievement has been set up.']);
        break;

    case 'setup_rank_restricted_product':
        // Find a product with SKU PWT-RANK-LOCK
        $product_id = wc_get_product_id_by_sku('PWT-RANK-LOCK');
        if (!$product_id) {
            // If it doesn't exist, create it
            $product = new WC_Product_Simple();
            $product->set_name('Rank Locked Product');
            $product->set_sku('PWT-RANK-LOCK');
            $product->set_regular_price('10.00');
            $product->set_virtual(true);
            $product_id = $product->save();
        }
        
        // Update the product's post meta to set the required rank to gold
        update_post_meta($product_id, '_required_rank', 'gold');
        
        echo json_encode(['success' => true, 'message' => "Rank restricted product with SKU PWT-RANK-LOCK (ID: {$product_id}) has been set up with gold rank requirement.", 'product_id' => $product_id]);
        break;

    case 'get_product_required_rank':
        $product_id = (int) ($_POST['product_id'] ?? 0);
        if (empty($product_id)) {
            echo json_encode(['success' => false, 'message' => 'Product ID parameter is missing.']);
            exit;
        }
        
        $required_rank = get_post_meta($product_id, '_required_rank', true);
        echo json_encode(['success' => true, 'required_rank' => $required_rank]);
        break;

    case 'get_user_rank':
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email parameter is missing.']);
            exit;
        }
        
        $user = get_user_by('email', $email);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }
        
        $user_id = $user->ID;
        $lifetime_points = get_user_meta($user_id, '_canna_lifetime_points', true);
        $current_rank_key = get_user_meta($user_id, '_canna_current_rank_key', true);
        
        echo json_encode([
            'success' => true, 
            'user_id' => $user_id,
            'lifetime_points' => $lifetime_points,
            'current_rank_key' => $current_rank_key
        ]);
        break;

    case 'get_rank_restricted_product_id':
        // Find a product with SKU PWT-RANK-LOCK
        $product_id = wc_get_product_id_by_sku('PWT-RANK-LOCK');
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Product with SKU PWT-RANK-LOCK not found.']);
        } else {
            echo json_encode(['success' => true, 'product_id' => $product_id]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing action parameter.']);
        break;
}

exit;