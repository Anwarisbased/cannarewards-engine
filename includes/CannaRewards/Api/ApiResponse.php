<?php
namespace CannaRewards\Api;

use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * API Response Formatter
 *
 * A final, static utility class that is the single source of truth for creating
 * consistent WP_REST_Response objects. This ensures all API output, both success
 * and error, has a predictable and standardized structure.
 */
final class ApiResponse {

    /**
     * Creates a standardized success response.
     *
     * @param array $data The data payload to be included.
     * @param int $status The HTTP status code (e.g., 200 OK, 201 Created).
     * @return WP_REST_Response
     */
    public static function success(array $data, int $status = 200): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'data'    => $data,
        ], $status);
    }

    /**
     * Creates a standardized error response.
     *
     * @param string $message A human-readable error message.
     * @param string $code A machine-readable error code (e.g., 'invalid_code').
     * @param int $status The HTTP status code (e.g., 400, 404, 500).
     * @return WP_Error  <-- THIS IS THE FIX. It now correctly returns a WP_Error object.
     */
    public static function error(string $message, string $code, int $status = 400): WP_Error {
        // The WordPress REST server knows how to automatically convert a WP_Error
        // object into a proper JSON error response. This is the correct way.
        return new WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * Helper for a generic "Not Found" error.
     *
     * @param string $message The specific message for what was not found.
     * @return WP_Error
     */
    public static function not_found(string $message = 'The requested resource could not be found.'): WP_Error {
        return self::error($message, 'not_found', 404);
    }

    /**
     * Helper for a generic "Forbidden" or authorization error.
     *
     * @param string $message The reason for the failure.
     * @return WP_Error
     */
    public static function forbidden(string $message = 'You do not have permission to perform this action.'): WP_Error {
        return self::error($message, 'forbidden', 403);
    }

    /**
     * Helper for a generic "Bad Request" or validation error.
     *
     * @param string $message The reason for the failure.
     * @return WP_Error
     */
    public static function bad_request(string $message = 'The request was malformed or is missing required parameters.'): WP_Error {
        return self::error($message, 'bad_request', 400);
    }
}