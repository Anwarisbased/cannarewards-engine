<?php
namespace CannaRewards\DTO;

final class SettingsDTO {
    public function __construct(
        // General
        public readonly string $frontendUrl,
        public readonly string $supportEmail,
        public readonly int $welcomeRewardProductId,
        public readonly int $referralSignupGiftId,
        public readonly string $referralBannerText,

        // Personality
        public readonly string $pointsName,
        public readonly string $rankName,
        public readonly string $welcomeHeaderText,
        public readonly string $scanButtonCta
        // Add theme settings if needed
    ) {}
}