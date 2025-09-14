<?php
namespace CannaRewards\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "SessionUser",
    description: "A lightweight object representing the core data for an authenticated user's session."
)]
final class SessionUserDTO {
    public function __construct(
        #[OA\Property(type: "integer", example: 123)]
        public readonly int $id,

        #[OA\Property(type: "string", example: "Jane", nullable: true)]
        public readonly ?string $firstName,
        
        #[OA\Property(type: "string", example: "Doe", nullable: true)]
        public readonly ?string $lastName,

        #[OA\Property(type: "string", format: "email", example: "jane.doe@example.com")]
        public readonly string $email,
        
        #[OA\Property(type: "integer", example: 1250)]
        public readonly int $points_balance,

        #[OA\Property(
            description: "The user's current rank.",
            properties: [
                new OA\Property(property: 'key', type: 'string', example: 'silver'),
                new OA\Property(property: 'name', type: 'string', example: 'Silver'),
            ],
            type: 'object'
        )]
        public readonly RankDTO $rank,

        #[OA\Property(type: "array", items: new OA\Items(type: "string"))]
        public readonly array $shipping,

        #[OA\Property(type: "string", example: "JANE1A2B", nullable: true)]
        public readonly ?string $referral_code,

        #[OA\Property(type: "integer", example: 2, description: "Tracks the user's progress in the onboarding flow.")]
        public readonly int $onboarding_quest_step,

        #[OA\Property(
            type: "object",
            description: "Flags for A/B testing frontend features.",
            example: ["dashboard_version" => "B"]
        )]
        public readonly object $feature_flags
    ) {}
}