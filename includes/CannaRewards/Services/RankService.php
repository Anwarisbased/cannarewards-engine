<?php
namespace CannaRewards\Services;

use CannaRewards\Domain\ValueObjects\Points;
use CannaRewards\Domain\ValueObjects\RankKey;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\DTO\RankDTO;
use CannaRewards\Infrastructure\WordPressApiWrapper;

final class RankService {
    private UserRepository $userRepository;
    private WordPressApiWrapper $wp;
    private ?array $rankStructureCache = null;

    public function __construct(UserRepository $userRepository, WordPressApiWrapper $wp) {
        $this->userRepository = $userRepository;
        $this->wp = $wp;
        // Clear the old cache to avoid serialization issues
        $this->wp->deleteTransient('canna_rank_structure_dtos_v2');
    }

    /**
     * Get the full RankDTO for a specific rank key.
     */
    public function getRankByKey(string $rankKey): ?RankDTO {
        $ranks = $this->getRankStructure();
        foreach ($ranks as $rank) {
            if ((string)$rank->key === $rankKey) {
                return $rank;
            }
        }
        return null;
    }

    public function getUserLifetimePoints(\CannaRewards\Domain\ValueObjects\UserId $userId): int {
        return $this->userRepository->getLifetimePoints($userId);
    }

    public function getUserRank(\CannaRewards\Domain\ValueObjects\UserId $userId): RankDTO {
        $lifetimePoints = $this->userRepository->getLifetimePoints($userId);
        $ranks = $this->getRankStructure();

        foreach ($ranks as $rank) {
            if ($lifetimePoints >= $rank->pointsRequired->toInt()) {
                return $rank; // The first one we hit is the correct one due to DESC sorting
            }
        }
        
        // This will find the 'member' rank DTO from the structure, or a default if not found
        return $this->getRankByKey('member');
    }

    public function getRankStructure(): array {
        if ($this->rankStructureCache !== null) {
            return $this->rankStructureCache;
        }

        $cachedRanks = $this->wp->getTransient('canna_rank_structure_dtos_v2'); // Use a new cache key
        if (is_array($cachedRanks)) {
            $this->rankStructureCache = $cachedRanks;
            return $this->rankStructureCache;
        }

        $ranks = [];
        $args = [
            'post_type'      => 'canna_rank',
            'posts_per_page' => -1,
            'meta_key'       => 'points_required',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'post_status'    => 'publish',
        ];
        $rankPosts = $this->wp->getPosts($args);

        foreach ($rankPosts as $post) {
            $dto = new RankDTO(
                key: RankKey::fromString($post->post_name),
                name: $post->post_title,
                pointsRequired: Points::fromInt((int) $this->wp->getPostMeta($post->ID, 'points_required', true)),
                pointMultiplier: (float) $this->wp->getPostMeta($post->ID, 'point_multiplier', true) ?: 1.0
            );
            $ranks[] = $dto;
        }

        $memberRank = new RankDTO(
            key: RankKey::fromString('member'),
            name: 'Member',
            pointsRequired: Points::fromInt(0),
            pointMultiplier: 1.0 // Members get a 1.0x multiplier
        );
        $ranks[] = $memberRank;

        // Ensure ranks are unique and sorted correctly
        $uniqueRanks = [];
        foreach ($ranks as $rank) {
            $uniqueRanks[(string)$rank->key] = $rank;
        }
        $ranks = array_values($uniqueRanks);
        usort($ranks, fn($a, $b) => $b->pointsRequired->toInt() <=> $a->pointsRequired->toInt());
        
        $this->wp->setTransient('canna_rank_structure_dtos_v2', $ranks, 12 * HOUR_IN_SECONDS);
        $this->rankStructureCache = $ranks;

        return $this->rankStructureCache;
    }
}