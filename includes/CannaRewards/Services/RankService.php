<?php
namespace CannaRewards\Services;

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
    }

    /**
     * Get the full RankDTO for a specific rank key.
     */
    public function getRankByKey(string $rankKey): ?RankDTO {
        $ranks = $this->getRankStructure();
        foreach ($ranks as $rank) {
            if ($rank->key === $rankKey) {
                return $rank;
            }
        }
        return null;
    }

    public function getUserRank(int $userId): RankDTO {
        $lifetimePoints = $this->userRepository->getLifetimePoints($userId);
        $ranks = $this->getRankStructure();

        foreach ($ranks as $rank) {
            if ($lifetimePoints >= $rank->points) {
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
                key: $post->post_name,
                name: $post->post_title,
                points: (int) $this->wp->getPostMeta($post->ID, 'points_required', true),
                point_multiplier: (float) $this->wp->getPostMeta($post->ID, 'point_multiplier', true) ?: 1.0
            );
            $ranks[] = $dto;
        }

        $memberRank = new RankDTO(
            key: 'member',
            name: 'Member',
            points: 0,
            point_multiplier: 1.0 // Members get a 1.0x multiplier
        );
        $ranks[] = $memberRank;

        // Ensure ranks are unique and sorted correctly
        $uniqueRanks = [];
        foreach ($ranks as $rank) {
            $uniqueRanks[$rank->key] = $rank;
        }
        $ranks = array_values($uniqueRanks);
        usort($ranks, fn($a, $b) => $b->points <=> $a->points);
        
        $this->wp->setTransient('canna_rank_structure_dtos_v2', $ranks, 12 * HOUR_IN_SECONDS);
        $this->rankStructureCache = $ranks;

        return $this->rankStructureCache;
    }
}