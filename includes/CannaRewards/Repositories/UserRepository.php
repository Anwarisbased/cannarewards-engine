<?php
namespace CannaRewards\Repositories;

use CannaRewards\Infrastructure\WordPressApiWrapper;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * User Repository
 *
 * Handles all data access logic for users. This is the single source of truth
 * for fetching and persisting user data, abstracting away the underlying
 * WordPress user and usermeta table implementation.
 */
class UserRepository {
    private WordPressApiWrapper $wp;

    public function __construct(WordPressApiWrapper $wp) {
        $this->wp = $wp;
    }
    
    public function getPointsBalance(int $user_id): int {
        $balance = $this->wp->getUserMeta($user_id, '_canna_points_balance', true);
        return empty($balance) ? 0 : (int) $balance;
    }

    public function getLifetimePoints(int $user_id): int {
        $lifetime_points = $this->wp->getUserMeta($user_id, '_canna_lifetime_points', true);
        return empty($lifetime_points) ? 0 : (int) $lifetime_points;
    }

    public function getCurrentRankKey(int $user_id): string {
        $rank_key = $this->wp->getUserMeta($user_id, '_canna_current_rank_key', true);
        return empty($rank_key) ? 'member' : $rank_key;
    }
    
    public function getReferralCode(int $user_id): ?string {
        $code = $this->wp->getUserMeta($user_id, '_canna_referral_code', true);
        return empty($code) ? null : $code;
    }

    public function findUserIdByReferralCode(string $referral_code): ?int {
        $users = $this->wp->findUsers([
            'meta_key'   => '_canna_referral_code',
            'meta_value' => sanitize_text_field($referral_code),
            'number'     => 1,
            'fields'     => 'ID',
        ]);
        return !empty($users) ? (int) $users[0] : null;
    }

    public function getReferringUserId(int $user_id): ?int {
        $referrer_id = $this->wp->getUserMeta($user_id, '_canna_referred_by_user_id', true);
        return !empty($referrer_id) ? (int) $referrer_id : null;
    }

    public function savePointsAndRank(int $user_id, int $new_balance, int $new_lifetime_points, string $new_rank_key): void {
        $this->wp->updateUserMeta($user_id, '_canna_points_balance', $new_balance);
        $this->wp->updateUserMeta($user_id, '_canna_lifetime_points', $new_lifetime_points);
        $this->wp->updateUserMeta($user_id, '_canna_current_rank_key', $new_rank_key);
    }
    
    public function saveReferralCode(int $user_id, string $code): void {
        $this->wp->updateUserMeta($user_id, '_canna_referral_code', $code);
    }
    
    public function setReferredBy(int $new_user_id, int $referrer_user_id): void {
        $this->wp->updateUserMeta($new_user_id, '_canna_referred_by_user_id', $referrer_user_id);
    }
    
    public function saveShippingAddress(int $user_id, array $shipping_details): void {
        if (empty($shipping_details) || !isset($shipping_details['firstName'])) {
            return;
        }

        $meta_map = [
            'firstName' => 'shipping_first_name',
            'lastName'  => 'shipping_last_name',
            'address1'  => 'shipping_address_1',
            'city'      => 'shipping_city',
            'state'     => 'shipping_state',
            'zip'       => 'shipping_postcode',
        ];

        foreach ($meta_map as $frontend_key => $meta_key) {
            if (isset($shipping_details[$frontend_key])) {
                $this->wp->updateUserMeta($user_id, $meta_key, sanitize_text_field($shipping_details[$frontend_key]));
            }
        }
        
        $this->wp->updateUserMeta( $user_id, 'billing_first_name', sanitize_text_field( $shipping_details['firstName'] ?? '' ) );
        $this->wp->updateUserMeta( $user_id, 'billing_last_name', sanitize_text_field( $shipping_details['lastName'] ?? '' ) );
    }
}