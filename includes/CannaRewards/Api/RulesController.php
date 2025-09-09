<?php
namespace CannaRewards\Api;

use CannaRewards\Services\RuleConditionRegistryService;

final class RulesController {
    private RuleConditionRegistryService $registry;

    public function __construct(RuleConditionRegistryService $registry) {
        $this->registry = $registry;
    }

    /**
     * API callback to get the list of all available rule builder conditions.
     * This is used to populate the UI in the WordPress admin.
     */
    public function get_conditions(): \WP_REST_Response {
        $conditions = $this->registry->getConditions();
        return new \WP_REST_Response($conditions, 200);
    }
}