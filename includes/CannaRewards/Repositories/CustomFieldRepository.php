<?php
namespace CannaRewards\Repositories;

use CannaRewards\Infrastructure\WordPressApiWrapper;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles all data access for Custom Field definitions.
 */
class CustomFieldRepository {
    private WordPressApiWrapper $wp;
    private const CACHE_KEY = 'canna_custom_fields_definition';

    public function __construct(WordPressApiWrapper $wp) {
        $this->wp = $wp;
    }

    /**
     * @return array The definitions for all published custom fields.
     */
    public function getFieldDefinitions(): array {
        $cached_fields = $this->wp->getTransient(self::CACHE_KEY);
        if (is_array($cached_fields)) {
            return $cached_fields;
        }

        $fields = [];
        $args = [
            'post_type'      => 'canna_custom_field',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];
        $field_posts = $this->wp->getPosts($args);

        foreach ($field_posts as $post) {
            $options_raw = $this->wp->getPostMeta($post->ID, 'options', true);
            $fields[] = [
                'key'       => $this->wp->getPostMeta($post->ID, 'meta_key', true),
                'label'     => $post->post_title,
                'type'      => $this->wp->getPostMeta($post->ID, 'field_type', true),
                'options'   => !empty($options_raw) ? preg_split('/\\r\\n|\\r|\\n/', $options_raw) : [],
                'display'   => (array) $this->wp->getPostMeta($post->ID, 'display_location', true),
            ];
        }

        $this->wp->setTransient(self::CACHE_KEY, $fields, 12 * HOUR_IN_SECONDS);
        return $fields;
    }
}