<?php
namespace CannaRewards\Domain;

final class MetaKeys {
    // User Meta
    const POINTS_BALANCE     = '_canna_points_balance';
    const LIFETIME_POINTS    = '_canna_lifetime_points';
    const CURRENT_RANK_KEY   = '_canna_current_rank_key';
    const REFERRAL_CODE      = '_canna_referral_code';
    const REFERRED_BY_USER_ID = '_canna_referred_by_user_id';
    
    // Product Meta
    const POINTS_AWARD       = 'points_award';
    const POINTS_COST        = 'points_cost';
    const REQUIRED_RANK      = '_required_rank';
    
    // Option Keys
    const MAIN_OPTIONS       = 'canna_rewards_options';
}