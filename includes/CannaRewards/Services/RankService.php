<?php
namespace CannaRewards\Services;

use CannaRewards\Repositories\UserRepository;
use CannaRewards\DTO\RankDTO;
use WP_Query;

final class RankService {
    private UserRepository $userRepository;
    private ?array $rankStructureCache = null;

    public function __construct(UserRepository $userRepository) {
        $this->userRepository = $userRepository;
    }

    /**
     * Gets the rank data for a specific user.
     *
     * @param int $userId The ID of the user.
     * @return RankDTO The user's current rank as a DTO.
     */
    public function getUserRank(int $userId): RankDTO {
        $lifetimePoints = $this->userRepository->getLifetimePoints($userId);
        $ranks = $this->getRankStructure();

        // Ranks are sorted high to low, so the first match is the correct one.
        foreach ($ranks as $rank) {
            if ($lifetimePoints >= $rank->points) {
                // To maintain object consistency, we should return a DTO that includes the multiplier.
                // We'll add this logic later. For now, we return the base DTO.
                return $rank;
            }
        }

        // This should theoretically never be hit if a 'member' rank with 0 points exists.
        $memberRank = new RankDTO();
        $memberRank->key = 'member';
        $memberRank->name = 'Member';
        $memberRank->points = 0; // Explicitly set points for the fallback.
        return $memberRank;
    }

    /**
     * Gets the entire rank structure for the application, sorted high to low by points.
     *
     * @return RankDTO[] An array of RankDTO objects.
     */
    public function getRankStructure(): array {
        if ($this->rankStructureCache !== null) {
            return $this->rankStructureCache;
        }

        $cachedRanks = get_transient('canna_rank_structure_dtos');
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
        ];
        $rankPosts = new WP_Query($args);

        if ($rankPosts->have_posts()) {
            while ($rankPosts->have_posts()) {
                $rankPosts->the_post();
                $postId = get_the_ID();
                $dto = new RankDTO();
                $dto->key = get_post_field('post_name', $postId);
                $dto->name = get_the_title();
                $dto->points = (int) get_post_meta($postId, 'points_required', true);
                // In the future, we would also add the multiplier here:
                // $dto->multiplier = (float) get_post_meta($postId, 'point_multiplier', true);
                $ranks[] = $dto;
            }
        }
        wp_reset_postdata();

        // Manually add the base 'Member' rank to ensure it always exists.
        $memberRank = new RankDTO();
        $memberRank->key = 'member';
        $memberRank->name = 'Member';
        $memberRank->points = 0;
        $ranks[] = $memberRank;

        // Sort again to ensure member rank is correctly placed if other ranks have 0 points.
        usort($ranks, fn($a, $b) => $b->points <=> $a->points);
        
        // Remove duplicate ranks (if 'member' was added manually and also exists as a CPT)
        $uniqueRanks = [];
        foreach ($ranks as $rank) {
            $uniqueRanks[$rank->key] = $rank;
        }
        $ranks = array_values($uniqueRanks);


        set_transient('canna_rank_structure_dtos', $ranks, 12 * HOUR_IN_SECONDS);
        $this->rankStructureCache = $ranks;

        return $this->rankStructureCache;
    }
}