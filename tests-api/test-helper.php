<?php
/**
 * A helper script for Playwright tests to manipulate the database state.
 *
 * WARNING: THIS FILE SHOULD BE DELETED OR SECURED BEFORE GOING TO PRODUCTION.
 * It provides direct, unauthenticated access to modify database records.
 */

// Bootstrap WordPress to get access to its functions and database.
require_once dirname(__DIR__, 4) . '/wp-load.php';

// Set the response content type to JSON.
header('Content-Type: application/json');

// Get the requested action from the POST data.
$action = $_POST['action'] ?? '';

// Globalize the WordPress database object.
global $wpdb;

// Handle the requested action.
switch ($action) {

    /**
     * Resets a specific QR code to an unused state.
     * Deletes any existing entry for the code and re-inserts it as fresh.
     */
    case 'reset_qr_code':
        $code = sanitize_text_field($_POST['code'] ?? '');
        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Code parameter is missing.']);
            exit;
        }

        // Delete the code if it exists.
        $wpdb->delete($wpdb->prefix . 'canna_reward_codes', ['code' => $code]);

        // Re-insert the code as a fresh, unused entry linked to our test SKU.
        $wpdb->insert($wpdb->prefix . 'canna_reward_codes', [
            'code' => $code,
            'sku'  => 'PWT-001', // SKU for the 'Playwright Scan Product'
        ]);

        echo json_encode(['success' => true, 'message' => "Code {$code} has been reset."]);
        break;

    /**
     * Resets a test user's state.
     * Deletes all of their orders and sets their point balance to a specific amount.
     */
    case 'reset_user_by_email':
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email parameter is missing.']);
            exit;
        }

        $user = get_user_by('email', $email);
        if ($user) {
            // Delete all existing WooCommerce orders for this user to ensure a clean slate.
            if (class_exists('WooCommerce')) {
                $orders = wc_get_orders(['customer' => $email]);
                foreach ($orders as $order) {
                    $order->delete(true); // `true` bypasses the trash.
                }
            }

            // Set their points balance to a specific amount (defaults to 0).
            $points = isset($_POST['points_balance']) ? absint($_POST['points_balance']) : 0;
            update_user_meta($user->ID, '_canna_points_balance', $points);

            echo json_encode(['success' => true, 'message' => "User {$email} has been reset with {$points} points."]);
        } else {
            // If user doesn't exist, just return success so the test can proceed to create it.
            echo json_encode(['success' => true, 'message' => "User {$email} not found, proceeding."]);
        }
        break;

    /**
     * Default case for an unknown action.
     */
    default:
        // Set a 400 Bad Request status code.
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing action parameter.']);
        break;
}

// Exit cleanly.
exit;