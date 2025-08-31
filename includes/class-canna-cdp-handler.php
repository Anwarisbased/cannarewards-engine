<?php
/**
 * Canna CDP (Customer Data Platform) Handler
 *
 * This class serves as a centralized gateway for all communication with the
 * third-party CDP (e.g., Customer.io). It is responsible for formatting
 * and sending enriched user and event data.
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_CDP_Handler {

    /**
     * Sends an event to the CDP for a specific user.
     *
     * This is the primary method for tracking user actions. It identifies the user
     * in the CDP and sends an event with an enriched data payload.
     *
     * @param int    $user_id     The WordPress user ID.
     * @param string $event_name  The name of the event (e.g., 'user_acquired', 'product_scanned').
     * @param array  $event_data  An associative array of data related to the event.
     */
    public static function send_event($user_id, $event_name, $event_data = []) {
        $user = get_userdata($user_id);
        if (!$user) {
            return; // Don't proceed if the user doesn't exist.
        }

        // In a real implementation, this is where you would get your API keys.
        // $site_id = get_option('customer_io_site_id');
        // $api_key = get_option('customer_io_api_key');
        // if (empty($site_id) || empty($api_key)) {
        //     error_log("CannaRewards CDP Handler: Customer.io API credentials are not set.");
        //     return;
        // }

        $payload = [
            'name' => $event_name,
            'data' => $event_data,
        ];

        // For now, we will log the event to the debug log instead of making a real API call.
        // This allows us to develop and test the event structure without needing live credentials.
        error_log('[CannaRewards CDP Event] User ID: ' . $user_id . ' | Event: ' . $event_name . ' | Payload: ' . json_encode($payload));

        /*
        // --- REAL IMPLEMENTATION EXAMPLE ---
        $api_url = "https://track.customer.io/api/v1/customers/{$user->user_email}/events";

        $response = wp_remote_post($api_url, [
            'method'    => 'POST',
            'headers'   => [
                'Authorization' => 'Basic ' . base64_encode("$site_id:$api_key"),
                'Content-Type'  => 'application/json',
            ],
            'body'      => json_encode($payload),
            'timeout'   => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('CannaRewards CDP Handler: Failed to send event to Customer.io. WP_Error: ' . $response->get_error_message());
        }
        // --- END REAL IMPLEMENTATION EXAMPLE ---
        */
    }
}