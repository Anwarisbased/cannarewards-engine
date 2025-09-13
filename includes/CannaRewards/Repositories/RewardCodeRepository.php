<?php
namespace CannaRewards\Repositories;

use CannaRewards\Infrastructure\WordPressApiWrapper;

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
    private WordPressApiWrapper $wp;
    private string $table_name = 'canna_reward_codes';

    public function __construct(WordPressApiWrapper $wp) {
        $this->wp = $wp;
    }

    /**
     * Finds a valid, unused reward code.
     *
     * @return object|null The code data object or null if not found.
     */
    public function findValidCode(string $code_to_claim): ?object {
        $full_table_name = $this->wp->getDbPrefix() . $this->table_name;
        $query = $this->wp->dbPrepare(
            "SELECT id, sku FROM {$full_table_name} WHERE code = %s AND is_used = 0",
            $code_to_claim
        );
        return $this->wp->dbGetRow($query);
    }

    /**
     * Marks a reward code as used by a specific user.
     */
    public function markCodeAsUsed(int $code_id, int $user_id): void {
        $this->wp->dbUpdate(
            $this->table_name,
            [
                'is_used'    => 1,
                'user_id'    => $user_id,
                'claimed_at' => current_time('mysql', 1)
            ],
            ['id' => $code_id]
        );
    }
    
    public function generateCodes(string $sku, int $quantity): array {
        $generated_codes = [];
        for ($i = 0; $i < $quantity; $i++) {
            $new_code = strtoupper($sku) . '-' . $this->wp->generatePassword(12, false, false);
            $this->wp->dbInsert($this->table_name, ['code' => $new_code, 'sku' => $sku]);
            $generated_codes[] = $new_code;
        }
        return $generated_codes;
    }
}