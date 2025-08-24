<?php
/**
 * Handles the registration of custom meta fields for CPTs.
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_Custom_Fields {

    /**
     * Initializes the class by hooking into the 'init' action.
     */
    public static function init() {
        add_action('init', [self::class, 'register_meta_fields']);
    }

    /**
     * Registers all custom meta fields for the plugin.
     * This ensures they are properly exposed to the REST API.
     */
    public static function register_meta_fields() {
        // Meta fields for 'product' post type (WooCommerce)
        register_post_meta('product', 'points_cost', [
            'type'              => 'integer',
            'description'       => 'The cost of the reward in points.',
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => 'absint' // Sanitize as integer
        ]);

        register_post_meta('product', '_required_rank', [
            'type'              => 'string',
            'description'       => 'The rank slug required to redeem this reward.',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_key' // Sanitize as a slug
        ]);

        register_post_meta('product', 'marketing_snippet', [
            'type'              => 'string',
            'description'       => 'A short marketing description for use in CDP events.',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        // Meta fields for 'canna_rank' post type
        register_post_meta('canna_rank', 'point_multiplier', [
            'type'              => 'number',
            'description'       => 'The point multiplier for this rank (e.g., 1.5 for 1.5x points).',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'floatval' // Sanitize as a float
        ]);
    }
}