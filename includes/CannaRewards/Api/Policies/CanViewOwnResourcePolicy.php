<?php
namespace CannaRewards\Api\Policies;
use WP_REST_Request;

class CanViewOwnResourcePolicy implements ApiPolicyInterface {
    public function can(WP_REST_Request $request): bool {
        $route_user_id = (int) $request->get_param('user_id');
        $current_user_id = get_current_user_id();

        if ($current_user_id === 0) {
            return false; // Not logged in
        }

        // Admins can do anything
        if (user_can($current_user_id, 'manage_options')) {
            return true;
        }

        return $current_user_id === $route_user_id;
    }
}