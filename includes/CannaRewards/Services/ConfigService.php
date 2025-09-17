<?php
namespace CannaRewards\Services;

use CannaRewards\Repositories\SettingsRepository;
use CannaRewards\Infrastructure\WordPressApiWrapper;

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
    private WordPressApiWrapper $wp;
    private SettingsRepository $settingsRepo;

    public function __construct(
        RankService $rankService, 
        WordPressApiWrapper $wp,
        SettingsRepository $settingsRepo
    ) {
        $this->rankService = $rankService;
        $this->wp = $wp;
        $this->settingsRepo = $settingsRepo;
    }

    public function getWelcomeRewardProductId(): int {
        return $this->settingsRepo->getSettings()->welcomeRewardProductId;
    }

    public function getReferralSignupGiftId(): int {
        return $this->settingsRepo->getSettings()->referralSignupGiftId;
    }

    public function canUsersRegister(): bool {
        return (bool) $this->wp->getOption('users_can_register');
    }

    public function areTermsAndConditionsEnabled(): bool {
        // For now, return true to require terms and conditions
        // This could be made configurable via WordPress options in the future
        return true;
    }

    public function isRegistrationEnabled(): bool {
        return $this->canUsersRegister();
    }

    /**
     * Assembles the complete application configuration object for the frontend.
     */
    public function get_app_config(): array {
        $settings = $this->settingsRepo->getSettings();
        return [
            'settings'         => [
                'brand_personality' => [
                    'points_name'    => $settings->pointsName,
                    'rank_name'      => $settings->rankName,
                    'welcome_header' => $settings->welcomeHeaderText,
                    'scan_cta'       => $settings->scanButtonCta,
                ],
                'theme'             => [
                    'primaryFont'        => $this->get_options()['theme_primary_font'] ?? null,
                    'radius'             => $this->get_options()['theme_radius'] ?? null,
                    'background'         => $this->get_options()['theme_background'] ?? null,
                    'foreground'         => $this->get_options()['theme_foreground'] ?? null,
                    'card'               => $this->get_options()['theme_card'] ?? null,
                    'primary'            => $this->get_options()['theme_primary'] ?? null,
                    'primary-foreground' => $this->get_options()['theme_primary_foreground'] ?? null,
                    'secondary'          => $this->get_options()['theme_secondary'] ?? null,
                    'destructive'        => $this->get_options()['theme_destructive'] ?? null,
                ],
            ],
            'all_ranks'        => $this->get_all_ranks(),
            'all_achievements' => $this->get_all_achievements(),
        ];
    }

    private function get_options(): array {
        static $options_cache = [];
        if (empty($options_cache)) {
            $options_cache = $this->wp->getOption('canna_rewards_options', []);
        }
        return $options_cache;
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