<?php
namespace CannaRewards\Services;

use CannaRewards\Infrastructure\WordPressApiWrapper;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Content Service
 *
 * Handles fetching and formatting of standard WordPress content like pages.
 */
class ContentService {
    private WordPressApiWrapper $wp;

    public function __construct(WordPressApiWrapper $wp) {
        $this->wp = $wp;
    }

    /**
     * Retrieves a WordPress page by its slug and formats it for the API.
     *
     * @param string $slug The slug of the page to retrieve.
     * @return array|null An array with page data or null if not found.
     */
    public function get_page_by_slug( string $slug ): ?array {
        // REFACTOR: Use the wrapper
        $page = $this->wp->getPageByPath( $slug, OBJECT, 'page' );

        if ( ! $page ) {
            return null; // Return null if no page is found.
        }

        // REFACTOR: Use the wrapper
        $content = $this->wp->applyFilters( 'the_content', $page->post_content );
        
        // Remove extra paragraphs that WordPress sometimes adds around content.
        $content = str_replace( ']]>', ']]&gt;', $content );

        // Return a clean, formatted array for the API response.
        return [
            'title'   => $page->post_title,
            'content' => $content,
        ];
    }
}