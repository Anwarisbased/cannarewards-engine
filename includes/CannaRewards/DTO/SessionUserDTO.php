<?php
namespace CannaRewards\DTO;

// The DTO for the user session. It contains another DTO.
final class SessionUserDTO {
    public int $id;
    public ?string $firstName;
    public ?string $lastName;
    public string $email;
    public int $points_balance;
    public RankDTO $rank; // Type-hinting the nested DTO
    public array $shipping;
    public ?string $referral_code;
    public int $onboarding_quest_step;
    public object $feature_flags;
}