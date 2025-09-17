<?php
namespace CannaRewards\DTO;

use CannaRewards\Domain\ValueObjects\EmailAddress;
use CannaRewards\Domain\ValueObjects\Points;
use CannaRewards\Domain\ValueObjects\UserId;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "SessionUser",
    description: "A lightweight object representing the core data for an authenticated user's session."
)]
final class SessionUserDTO {
    public function __construct(
        #[OA\Property(type: "integer", example: 123)]
        public readonly UserId $id,

        #[OA\Property(type: "string", example: "Jane", nullable: true)]
        public readonly string $firstName,
        
        #[OA\Property(type: "string", example: "Doe", nullable: true)]
        public readonly ?string $lastName,

        #[OA\Property(type: "string", format: "email", example: "jane.doe@example.com")]
        public readonly EmailAddress $email,
        
        #[OA\Property(type: "integer", example: 1250)]
        public readonly Points $pointsBalance,

        #[OA\Property(ref: "#/components/schemas/Rank")]
        public readonly RankDTO $rank,

        // ShippingAddress is now a DTO
        #[OA\Property(ref: "#/components/schemas/ShippingAddress")]
        public readonly ?ShippingAddressDTO $shippingAddress,

        #[OA\Property(
            type: "string",
            description: "User's unique referral code",
            example: "JANE1A2B",
            nullable: true
        )]
        public readonly ?string $referralCode,

        #[OA\Property(
            type: "object",
            description: "Flags for A/B testing frontend features.",
            example: ["dashboard_version" => "B"]
        )]
        public readonly object $featureFlags
    ) {}
}