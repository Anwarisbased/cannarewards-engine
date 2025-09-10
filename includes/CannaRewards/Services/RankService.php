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

    public function getUserRank(int $userId): RankDTO {
        $lifetimePoints = $this->userRepository->getLifetimePoints($userId);
        $ranks = $this->getRankStructure();

        foreach ($ranks as $rank) {
            if ($lifetimePoints >= $rank->points) {
                return $rank;
            }
        }

        $memberRank = new RankDTO();
        $memberRank->key = 'member';
        $memberRank->name = 'Member';
        $memberRank->points = 0;
        return $memberRank;
    }

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
            // --- THE FIX ---
            // This ensures we only query for ranks that are explicitly published,
            // ignoring auto-drafts and other statuses.
            'post_status'    => 'publish',
            // --- END FIX ---
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
                $ranks[] = $dto;
            }
        }
        wp_reset_postdata();

        $memberRank = new RankDTO();
        $memberRank->key = 'member';
        $memberRank->name = 'Member';
        $memberRank->points = 0;
        $ranks[] = $memberRank;

        usort($ranks, fn($a, $b) => $b->points <=> $a->points);
        
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