<?php
namespace CannaRewards\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "SessionUser",
    description: "A lightweight object representing the core data for an authenticated user's session."
)]
final class SessionUserDTO {
    #[OA\Property(type: "integer", example: 123)]
    public int $id;

    #[OA\Property(type: "string", example: "Jane", nullable: true)]
    public ?string $firstName;
    
    #[OA\Property(type: "string", example: "Doe", nullable: true)]
    public ?string $lastName;

    #[OA\Property(type: "string", format: "email", example: "jane.doe@example.com")]
    public string $email;
    
    #[OA\Property(type: "integer", example: 1250)]
    public int $points_balance;

    #[OA\Property(
        description: "The user's current rank.",
        properties: [
            new OA\Property(property: 'key', type: 'string', example: 'silver'),
            new OA\Property(property: 'name', type: 'string', example: 'Silver'),
        ],
        type: 'object'
    )]
    public RankDTO $rank;

    #[OA\Property(type: "array", items: new OA\Items(type: "string"))]
    public array $shipping;

    #[OA\Property(type: "string", example: "JANE1A2B", nullable: true)]
    public ?string $referral_code;

    #[OA\Property(type: "integer", example: 2, description: "Tracks the user's progress in the onboarding flow.")]
    public int $onboarding_quest_step;

    #[OA\Property(
        type: "object",
        description: "Flags for A/B testing frontend features.",
        example: ["dashboard_version" => "B"]
    )]
    public object $feature_flags;
}