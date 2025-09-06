<?php
namespace CannaRewards\Repositories;

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
    
    /**
     * Gets the current points balance for a given user.
     */
    public function getPointsBalance(int $user_id): int {
        $balance = get_user_meta($user_id, '_canna_points_balance', true);
        return empty($balance) ? 0 : (int) $balance;
    }

    /**
     * Gets the total lifetime points accumulated by a user.
     */
    public function getLifetimePoints(int $user_id): int {
        $lifetime_points = get_user_meta($user_id, '_canna_lifetime_points', true);
        return empty($lifetime_points) ? 0 : (int) $lifetime_points;
    }

    /**
     * Gets the current rank key for a user.
     */
    public function getCurrentRankKey(int $user_id): string {
        $rank_key = get_user_meta($user_id, '_canna_current_rank_key', true);
        return empty($rank_key) ? 'member' : $rank_key;
    }
    
    /**
     * Gets the user's referral code.
     */
    public function getReferralCode(int $user_id): ?string {
        $code = get_user_meta($user_id, '_canna_referral_code', true);
        return empty($code) ? null : $code;
    }

    /**
     * Finds the user ID of the referrer given a referral code.
     */
    public function findUserIdByReferralCode(string $referral_code): ?int {
        $users = get_users([
            'meta_key'   => '_canna_referral_code',
            'meta_value' => sanitize_text_field($referral_code),
            'number'     => 1,
            'fields'     => 'ID',
        ]);
        return !empty($users) ? (int) $users[0] : null;
    }

    /**
     * Gets the ID of the user who referred the given user.
     */
    public function getReferringUserId(int $user_id): ?int {
        $referrer_id = get_user_meta($user_id, '_canna_referred_by_user_id', true);
        return !empty($referrer_id) ? (int) $referrer_id : null;
    }

    /**
     * Persists all points and rank data for a user.
     */
    public function savePointsAndRank(int $user_id, int $new_balance, int $new_lifetime_points, string $new_rank_key): void {
        update_user_meta($user_id, '_canna_points_balance', $new_balance);
        update_user_meta($user_id, '_canna_lifetime_points', $new_lifetime_points);
        update_user_meta($user_id, '_canna_current_rank_key', $new_rank_key);
    }
    
    /**
     * Persists a user's referral code.
     */
    public function saveReferralCode(int $user_id, string $code): void {
        update_user_meta($user_id, '_canna_referral_code', $code);
    }
    
    /**
     * Links a new user to the user who referred them.
     */
    public function setReferredBy(int $new_user_id, int $referrer_user_id): void {
        update_user_meta($new_user_id, '_canna_referred_by_user_id', $referrer_user_id);
    }
    
    /**
     * Updates a user's shipping address.
     */
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
                update_user_meta($user_id, $meta_key, sanitize_text_field($shipping_details[$frontend_key]));
            }
        }
    }
}