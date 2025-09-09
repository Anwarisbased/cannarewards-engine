<?php
namespace CannaRewards\Services;

// This service is the single source of truth for what rules can be built in the UI.
final class RuleConditionRegistryService {
    private array $conditions = [];

    public function __construct() {
        $this->registerDefaultConditions();
    }

    /**
     * Registers a new condition that can be used in the rule builder UI.
     *
     * @param string $key The dot-notation path to the data in the event context.
     * @param string $label The human-readable label shown in the UI dropdown.
     * @param array $operators The operators valid for this data type (e.g., ['is', 'is_not']).
     * @param string $inputType The type of input to render in the UI ('text', 'number', 'select').
     * @param array $options For 'select' inputs, the available choices.
     */
    public function register(string $key, string $label, array $operators, string $inputType = 'text', array $options = []): void {
        $this->conditions[$key] = [
            'key' => $key,
            'label' => $label,
            'operators' => $operators,
            'inputType' => $inputType,
            'options' => $options,
        ];
    }

    /**
     * @return array A list of all registered rule conditions.
     */
    public function getConditions(): array {
        return array_values($this->conditions);
    }

    /**
     * This is where we define the entire "dictionary" of possible rules.
     * To add a new rule to the UI, a developer only needs to add it here.
     */
    private function registerDefaultConditions(): void {
        $this->register(
            'product_snapshot.taxonomy.strain_type',
            'Product Strain Type',
            ['is', 'is_not'],
            'select',
            ['Sativa', 'Indica', 'Hybrid']
        );

        $this->register(
            'user_snapshot.engagement.total_scans',
            "User's Total Scans",
            ['is', 'is_not', '>', '<'],
            'number'
        );
        
        $this->register(
            'user_snapshot.status.rank_key',
            "User's Rank",
            ['is', 'is_not'],
            'select',
            // In a real system, we'd get these from the RankService, but this is fine for now.
            ['member' => 'Member', 'bronze' => 'Bronze', 'silver' => 'Silver', 'gold' => 'Gold']
        );
    }
}