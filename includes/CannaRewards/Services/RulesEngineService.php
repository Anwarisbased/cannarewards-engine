<?php
namespace CannaRewards\Services;

final class RulesEngineService {
    /**
     * Evaluates a set of conditions against a context payload.
     *
     * @param array $conditions The array of rule conditions from the database.
     * @param array $context The live event data payload.
     * @return bool True if all conditions pass, false otherwise.
     */
    public function evaluate(array $conditions, array $context): bool {
        if (empty($conditions)) {
            return true; // No conditions means the rule always passes.
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateSingleCondition($condition, $context)) {
                return false; // If any single condition fails, the whole set fails.
            }
        }

        return true; // All conditions passed.
    }

    private function evaluateSingleCondition(array $condition, array $context): bool {
        if (!isset($condition['field'], $condition['operator'], $condition['value'])) {
            return false; // Malformed condition.
        }

        $actualValue = $this->getValueFromContext($condition['field'], $context);
        $expectedValue = $condition['value'];

        // If the data doesn't exist in the context, the condition automatically fails.
        if ($actualValue === null) {
            return false;
        }

        switch ($condition['operator']) {
            case 'is':
                return $actualValue == $expectedValue;
            case 'is_not':
                return $actualValue != $expectedValue;
            case '>':
                return (float)$actualValue > (float)$expectedValue;
            case '<':
                return (float)$actualValue < (float)$expectedValue;
            default:
                return false;
        }
    }

    /**
     * Safely gets a nested value from the context array using dot notation.
     * Example: 'user_snapshot.economy.points_balance'
     */
    private function getValueFromContext(string $fieldPath, array $context) {
        $keys = explode('.', $fieldPath);
        $value = $context;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}