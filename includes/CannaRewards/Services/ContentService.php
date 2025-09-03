<?php
namespace CannaRewards\Services;

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

    /**
     * Retrieves a WordPress page by its slug and formats it for the API.
     *
     * @param string $slug The slug of the page to retrieve.
     * @return array|null An array with page data or null if not found.
     */
    public function get_page_by_slug( string $slug ): ?array {
        // Use the core WordPress function to find the page post object.
        $page = get_page_by_path( $slug, OBJECT, 'page' );

        if ( ! $page ) {
            return null; // Return null if no page is found.
        }

        // Apply 'the_content' filter to process shortcodes and other formatting.
        $content = apply_filters( 'the_content', $page->post_content );
        
        // Remove extra paragraphs that WordPress sometimes adds around content.
        $content = str_replace( ']]>', ']]&gt;', $content );

        // Return a clean, formatted array for the API response.
        return [
            'title'   => $page->post_title,
            'content' => $content,
        ];
    }
}