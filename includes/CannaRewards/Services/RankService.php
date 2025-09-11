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

        $cachedRanks = $this->wp->getTransient('canna_rank_structure_dtos');
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
            $dto = new RankDTO();
            $dto->key = $post->post_name;
            $dto->name = $post->post_title;
            $dto->points = (int) $this->wp->getPostMeta($post->ID, 'points_required', true);
            $ranks[] = $dto;
        }

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

        $this->wp->setTransient('canna_rank_structure_dtos', $ranks, 12 * HOUR_IN_SECONDS);
        $this->rankStructureCache = $ranks;

        return $this->rankStructureCache;
    }
}