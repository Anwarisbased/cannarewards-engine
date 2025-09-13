<?php
namespace CannaRewards\Api\Policies;
use WP_REST_Request;

interface ApiPolicyInterface {
    public function can(WP_REST_Request $request): bool;
}