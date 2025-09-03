<?php
namespace CannaRewards\Services;

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

    /**
     * Assembles the complete application configuration object.
     */
    public function get_app_config(): array {
        return [
            'settings'         => $this->get_settings(),
            'all_ranks'        => $this->get_all_ranks(),
            'all_achievements' => $this->get_all_achievements(),
        ];
    }

    /**
     * Fetches and formats the brand personality and theme settings.
     */
    private function get_settings(): array {
        $options = get_option( 'canna_rewards_options', [] );
        return [
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
        ];
    }

    /**
     * Fetches and formats all defined Rank CPTs.
     */
    private function get_all_ranks(): array {
        return canna_get_rank_structure();
    }

    /**
     * Fetches and formats all defined Achievement CPTs from our custom table.
     */
    private function get_all_achievements(): array {
        $cached_achievements = get_transient('canna_all_achievements_v2');
        if ( is_array($cached_achievements) ) {
            return $cached_achievements;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_achievements';
        $results    = $wpdb->get_results( "SELECT achievement_key, title, description, rarity, icon_url FROM `{$table_name}` WHERE is_active = 1" );

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
        
        set_transient('canna_all_achievements_v2', $achievements, 12 * HOUR_IN_SECONDS);
        return $achievements;
    }
}