<?php
/**
 * Canna Rewards API Manager
 *
 * This class is responsible for loading and initializing all the specialized
 * API controller classes for the CannaRewards PWA.
 *
 * @package CannaRewards
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

class Canna_API_Manager {

    /**
     * Initializes the class by adding the main action for route registration.
     */
    public static function init() {
        // First, load all the individual controller classes.
        self::load_controllers();
        
        // Then, hook into rest_api_init to register their routes.
        add_action('rest_api_init', [self::class, 'register_all_routes']);
    }
    
    /**
     * Includes all the controller files.
     */
    private static function load_controllers() {
        $controller_path = CANNA_PLUGIN_DIR . 'includes/api/';
        require_once $controller_path . 'class-canna-auth-controller.php';
        require_once $controller_path . 'class-canna-referral-controller.php';
        require_once $controller_path . 'class-canna-user-controller.php';
        require_once $controller_path . 'class-canna-rewards-controller.php';
        require_once $controller_path . 'class-canna-content-controller.php';
        require_once $controller_path . 'class-canna-admin-controller.php';
    }

    /**
     * Calls the register_routes method on each specialized controller.
     */
    public static function register_all_routes() {
        Canna_Auth_Controller::register_routes();
        Canna_Referral_Controller::register_routes();
        Canna_User_Controller::register_routes();
        Canna_Rewards_Controller::register_routes();
        Canna_Content_Controller::register_routes();
        Canna_Admin_Controller::register_routes();
    }
}