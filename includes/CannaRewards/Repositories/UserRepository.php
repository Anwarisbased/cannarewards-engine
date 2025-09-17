<?php
namespace CannaRewards\Repositories;

use CannaRewards\Domain\MetaKeys;
use CannaRewards\Domain\ValueObjects\UserId;
use CannaRewards\DTO\ShippingAddressDTO;
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

    /**
     * Retrieves the core user object (\WP_User).
     * This is one of the few places where returning a WordPress-specific object is acceptable,
     * as it's the raw data source that services will adapt into DTOs.
     */
    public function getUserCoreData(UserId $userId): ?\WP_User {
        return $this->wp->getUserById($userId->toInt());
    }
    
    public function getUserCoreDataBy(string $field, string $value): ?\WP_User {
        return $this->wp->findUserBy($field, $value);
    }
    
    /**
     * Creates a new WordPress user.
     * @throws \Exception If user creation fails.
     * @return int The new user's ID.
     */
    public function createUser(\CannaRewards\Domain\ValueObjects\EmailAddress $email, \CannaRewards\Domain\ValueObjects\PlainTextPassword $password, string $firstName, string $lastName): int {
        $user_id = $this->wp->createUser([
            'user_login' => $email->value,
            'user_email' => $email->value,
            'user_pass'  => $password->value,  // Use the actual password value
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'role' => 'subscriber'
        ]);

        if (is_wp_error($user_id)) {
            throw new \Exception($user_id->get_error_message(), 500);
        }
        return (int) $user_id;
    }

    /**
     * Saves the initial meta fields for a newly registered user.
     */
    public function saveInitialMeta(UserId $userId, string $phone, bool $agreedToMarketing): void {
        $this->wp->updateUserMeta($userId->toInt(), 'phone_number', $phone);
        $this->wp->updateUserMeta($userId->toInt(), 'marketing_consent', $agreedToMarketing);
        $this->wp->updateUserMeta($userId->toInt(), '_age_gate_confirmed_at', current_time('mysql', 1));
    }

    /**
     * A generic proxy to the wrapper for fetching user meta.
     * Services should use this instead of accessing the wrapper directly for user data.
     */
    public function getUserMeta(UserId $userId, string $key, bool $single = true) {
        return $this->wp->getUserMeta($userId->toInt(), $key, $single);
    }

    public function getPointsBalance(UserId $userId): int {
        $balance = $this->wp->getUserMeta($userId->toInt(), MetaKeys::POINTS_BALANCE, true);
        return empty($balance) ? 0 : (int) $balance;
    }

    public function getLifetimePoints(UserId $userId): int {
        $lifetime_points = $this->wp->getUserMeta($userId->toInt(), MetaKeys::LIFETIME_POINTS, true);
        error_log("UserRepository: getLifetimePoints for user " . $userId->toInt() . " = " . (empty($lifetime_points) ? 0 : (int) $lifetime_points));
        return empty($lifetime_points) ? 0 : (int) $lifetime_points;
    }

    public function getCurrentRankKey(UserId $userId): string {
        $rank_key = $this->wp->getUserMeta($userId->toInt(), MetaKeys::CURRENT_RANK_KEY, true);
        return empty($rank_key) ? 'member' : $rank_key;
    }
    
    public function getReferralCode(UserId $userId): ?string {
        $code = $this->wp->getUserMeta($userId->toInt(), MetaKeys::REFERRAL_CODE, true);
        return empty($code) ? null : $code;
    }

    public function findUserIdByReferralCode(string $referral_code): ?int {
        $users = $this->wp->findUsers([
            'meta_key'   => MetaKeys::REFERRAL_CODE,
            'meta_value' => sanitize_text_field($referral_code),
            'number'     => 1,
            'fields'     => 'ID',
        ]);
        return !empty($users) ? (int) $users[0] : null;
    }

    public function getReferringUserId(UserId $userId): ?int {
        $referrer_id = $this->wp->getUserMeta($userId->toInt(), MetaKeys::REFERRED_BY_USER_ID, true);
        return !empty($referrer_id) ? (int) $referrer_id : null;
    }

    /**
     * Gets the user's shipping address as a formatted DTO.
     */
    public function getShippingAddressDTO(UserId $userId): ShippingAddressDTO {
        return new ShippingAddressDTO(
            firstName: $this->wp->getUserMeta($userId->toInt(), 'shipping_first_name', true),
            lastName: $this->wp->getUserMeta($userId->toInt(), 'shipping_last_name', true),
            address1: $this->wp->getUserMeta($userId->toInt(), 'shipping_address_1', true),
            city: $this->wp->getUserMeta($userId->toInt(), 'shipping_city', true),
            state: $this->wp->getUserMeta($userId->toInt(), 'shipping_state', true),
            postcode: $this->wp->getUserMeta($userId->toInt(), 'shipping_postcode', true)
        );
    }

    /**
     * Gets the user's shipping address as a simple associative array.
     */
    public function getShippingAddressArray(UserId $userId): array {
        return (array) $this->getShippingAddressDTO($userId);
    }

    public function savePointsAndRank(UserId $userId, int $new_balance, int $new_lifetime_points, string $new_rank_key): void {
        $this->wp->updateUserMeta($userId->toInt(), MetaKeys::POINTS_BALANCE, $new_balance);
        $this->wp->updateUserMeta($userId->toInt(), MetaKeys::LIFETIME_POINTS, $new_lifetime_points);
        $this->wp->updateUserMeta($userId->toInt(), MetaKeys::CURRENT_RANK_KEY, $new_rank_key);
    }
    
    public function saveReferralCode(UserId $userId, string $code): void {
        $this->wp->updateUserMeta($userId->toInt(), MetaKeys::REFERRAL_CODE, $code);
    }
    
    public function setReferredBy(UserId $newUserId, UserId $referrerUserId): void {
        $this->wp->updateUserMeta($newUserId->toInt(), MetaKeys::REFERRED_BY_USER_ID, $referrerUserId->toInt());
    }
    
    public function saveShippingAddress(UserId $userId, array $shipping_details): void {
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
                $this->wp->updateUserMeta($userId->toInt(), $meta_key, sanitize_text_field($shipping_details[$frontend_key]));
            }
        }
        
        $this->wp->updateUserMeta( $userId->toInt(), 'billing_first_name', sanitize_text_field( $shipping_details['firstName'] ?? '' ) );
        $this->wp->updateUserMeta( $userId->toInt(), 'billing_last_name', sanitize_text_field( $shipping_details['lastName'] ?? '' ) );
    }
    
    /**
     * Updates a user's core data (first name, last name, etc.).
     * @param UserId $userId The user ID
     * @param array $data Associative array of user data to update
     * @return int|\WP_Error The updated user's ID on success, or a WP_Error object on failure.
     */
    public function updateUserData(UserId $userId, array $data) {
        $data['ID'] = $userId->toInt();
        return $this->wp->updateUser($data);
    }
    
    /**
     * Updates a user meta field.
     * @param UserId $userId The user ID
     * @param string $meta_key The meta key to update
     * @param mixed $meta_value The meta value to set
     * @param mixed $prev_value Optional. Previous value to check before updating.
     * @return bool True on success, false on failure.
     */
    public function updateUserMetaField(UserId $userId, string $meta_key, $meta_value, $prev_value = '') {
        return $this->wp->updateUserMeta($userId->toInt(), $meta_key, $meta_value, $prev_value);
    }
}