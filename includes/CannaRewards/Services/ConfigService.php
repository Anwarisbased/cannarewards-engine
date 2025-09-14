<?php
namespace CannaRewards\Services;

use CannaRewards\Domain\MetaKeys;
use CannaRewards\Infrastructure\WordPressApiWrapper; // <<<--- IMPORT THE WRAPPER

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Config Service
 *
 * Gathers all static, global configuration data for the application.
 */
class ConfigService {
    private RankService $rankService;
    private WordPressApiWrapper $wp; // <<<--- ADD WRAPPER PROPERTY
    private array $options_cache = []; // Cache options for the request

    public function __construct(RankService $rankService, WordPressApiWrapper $wp) { // <<<--- INJECT WRAPPER
        $this->rankService = $rankService;
        $this->wp = $wp;
    }

    private function get_options(): array {
        if (empty($this->options_cache)) {
            $this->options_cache = $this->wp->getOption(MetaKeys::MAIN_OPTIONS, []);
        }
        return $this->options_cache;
    }

    // <<< --- ADD NEW, SPECIFIC GETTER METHODS ---
    public function getWelcomeRewardProductId(): int {
        $options = $this->get_options();
        return !empty($options['welcome_reward_product']) ? (int) $options['welcome_reward_product'] : 0;
    }

    public function getReferralSignupGiftId(): int {
        $options = $this->get_options();
        return !empty($options['referral_signup_gift']) ? (int) $options['referral_signup_gift'] : 0;
    }
    // --- END NEW GETTERS ---

    public function canUsersRegister(): bool {
        return (bool) $this->wp->getOption('users_can_register');
    }

    /**
     * Assembles the complete application configuration object for the frontend.
     */
    public function get_app_config(): array {
        $options = $this->get_options();
        return [
            'settings'         => [
                'brand_personality' => [
                    'points_name'    => $options['points_name'] ?? 'Points',
                    'rank_name'      => $options['rank_name'] ?? 'Rank',
                    'welcome_header' => $options['welcome_header'] ?? 'Welcome, {firstName}',
                    'scan_cta'       => $options['scan_cta'] ?? 'Scan Product',
                ],
                'theme'             => [
                    'primaryFont'        => $options['theme_primary_font'] ?? null,
                    'radius'             => $options['theme_radius'] ?? null,
                    'background'         => $options['theme_background'] ?? null,
                    'foreground'         => $options['theme_foreground'] ?? null,
                    'card'               => $options['theme_card'] ?? null,
                    'primary'            => $options['theme_primary'] ?? null,
                    'primary-foreground' => $options['theme_primary_foreground'] ?? null,
                    'secondary'          => $options['theme_secondary'] ?? null,
                    'destructive'        => $options['theme_destructive'] ?? null,
                ],
            ],
            'all_ranks'        => $this->get_all_ranks(),
            'all_achievements' => $this->get_all_achievements(),
        ];
    }

    private function get_all_ranks(): array {
        $rank_dtos = $this->rankService->getRankStructure();
        $ranks_for_api = [];
        foreach ($rank_dtos as $dto) {
            $rank_array = (array) $dto;
            $rank_array['benefits'] = [];
            $ranks_for_api[$dto->key] = $rank_array;
        }
        return $ranks_for_api;
    }

    private function get_all_achievements(): array {
        $cached_achievements = $this->wp->getTransient('canna_all_achievements_v2');
        if ( is_array($cached_achievements) ) {
            return $cached_achievements;
        }

        $table_name = 'canna_achievements';
        $results = $this->wp->dbGetResults("SELECT achievement_key, title, description, rarity, icon_url FROM `{$this->wp->db->prefix}{$table_name}` WHERE is_active = 1");

        $achievements = [];
        if ( ! empty($results) ) {
            foreach ( $results as $ach ) {
                $achievements[ $ach->achievement_key ] = [
                    'title'       => $ach->title,
                    'description' => $ach->description,
                    'rarity'      => $ach->rarity,
                    'icon_url'    => $ach->icon_url,
                ];
            }
        }
        
        $this->wp->setTransient('canna_all_achievements_v2', $achievements, 12 * HOUR_IN_SECONDS);
        return $achievements;
    }
}