<?php
namespace CannaRewards\Repositories;

use CannaRewards\DTO\SettingsDTO;
use CannaRewards\Infrastructure\WordPressApiWrapper;
use CannaRewards\Domain\MetaKeys;

final class SettingsRepository {
    private ?SettingsDTO $settingsCache = null;

    public function __construct(private WordPressApiWrapper $wp) {}

    public function getSettings(): SettingsDTO {
        if ($this->settingsCache !== null) {
            return $this->settingsCache; // Return from in-request cache
        }

        $options = $this->wp->getOption(MetaKeys::MAIN_OPTIONS, []);
        
        $dto = new SettingsDTO(
            frontendUrl: $options['frontend_url'] ?? home_url(),
            supportEmail: $options['support_email'] ?? get_option('admin_email'),
            welcomeRewardProductId: (int) ($options['welcome_reward_product'] ?? 0),
            referralSignupGiftId: (int) ($options['referral_signup_gift'] ?? 0),
            referralBannerText: $options['referral_banner_text'] ?? '',
            pointsName: $options['points_name'] ?? 'Points',
            rankName: $options['rank_name'] ?? 'Rank',
            welcomeHeaderText: $options['welcome_header'] ?? 'Welcome, {firstName}',
            scanButtonCta: $options['scan_cta'] ?? 'Scan Product'
        );

        $this->settingsCache = $dto; // Cache for the remainder of the request
        return $dto;
    }
}