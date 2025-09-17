<?php
namespace CannaRewards\Api;

use CannaRewards\Services\UserService;
use WP_REST_Request;
use OpenApi\Attributes as OA;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

#[OA\Info(title: "CannaRewards API", version: "2.1.0")]
/**
 * Handles the user session endpoint.
 */
class SessionController {
    private UserService $userService;

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }

    #[OA\Get(
        path: "/users/me/session",
        tags: ["App & Session"],
        summary: "Get Session Data",
        description: "A lightweight 'heartbeat' endpoint. Verifies the user's token and returns the minimal data needed to render the authenticated app shell.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: "#/components/schemas/SessionUser")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    #[OA\SecurityScheme(
        securityScheme: "bearerAuth",
        type: "http",
        bearerFormat: "JWT",
        scheme: "bearer"
    )]
    /**
     * Callback for GET /v2/users/me/session.
     * Fetches and returns the lightweight session data for the currently authenticated user.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return \WP_REST_Response The formatted API response.
     */
    public function get_session_data(WP_REST_Request $request): \WP_REST_Response {
        // <<<--- REFACTOR: Let the service figure out the user ID
        $session_dto = $this->userService->get_current_user_session_data();

        // Convert the DTO to an array, ensuring Value Objects are properly serialized
        // Match the OpenAPI spec structure
        $response_data = [
            'id' => $session_dto->id->toInt(),
            'firstName' => $session_dto->firstName,
            'lastName' => $session_dto->lastName,
            'email' => (string) $session_dto->email,
            'points_balance' => $session_dto->pointsBalance->toInt(),
            'rank' => [
                'key' => (string) $session_dto->rank->key,
                'name' => $session_dto->rank->name,
                'points' => $session_dto->rank->pointsRequired->toInt(),
                'point_multiplier' => $session_dto->rank->pointMultiplier
            ],
            'shipping' => $session_dto->shippingAddress ? [
                'first_name' => $session_dto->shippingAddress->firstName,
                'last_name' => $session_dto->shippingAddress->lastName,
                'address_1' => $session_dto->shippingAddress->address1,
                'city' => $session_dto->shippingAddress->city,
                'state' => $session_dto->shippingAddress->state,
                'postcode' => $session_dto->shippingAddress->postcode
            ] : null,
            'referral_code' => $session_dto->referralCode,
            'onboarding_quest_step' => 0, // This would need to be fetched from user meta
            'feature_flags' => $session_dto->featureFlags
        ];
        
        // Ensure feature_flags is an object, not an array, to match the OpenAPI contract.
        if (isset($response_data['feature_flags']) && is_array($response_data['feature_flags']) && empty($response_data['feature_flags'])) {
            $response_data['feature_flags'] = (object) $response_data['feature_flags'];
        }

        return ApiResponse::success($response_data);
    }
}
