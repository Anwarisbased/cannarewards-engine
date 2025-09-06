<?php
namespace CannaRewards\Repositories;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Reward Code Repository
 *
 * Handles all data access for reward QR codes.
 */
class RewardCodeRepository {

    /**
     * Finds a valid, unused reward code.
     *
     * @return object|null The code data object or null if not found.
     */
    public function findValidCode(string $code_to_claim): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_reward_codes';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, sku FROM {$table_name} WHERE code = %s AND is_used = 0",
            $code_to_claim
        ));
    }

    /**
     * Marks a reward code as used by a specific user.
     */
    public function markCodeAsUsed(int $code_id, int $user_id): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_reward_codes';
        
        $wpdb->update(
            $table_name,
            [
                'is_used'    => 1,
                'user_id'    => $user_id,
                'claimed_at' => current_time('mysql', 1)
            ],
            ['id' => $code_id]
        );
    }
}