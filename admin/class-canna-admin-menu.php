<?php
/**
 * Handles the Brand Settings admin menu page.
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_Admin_Menu {

    /**
     * Initializes the class by adding hooks to create the admin menu and register settings.
     */
    public static function init() {
        add_action('admin_menu', [self::class, 'add_admin_menu']);
        add_action('admin_init', [self::class, 'settings_init']);
    }

    /**
     * Adds the top-level "Brand Settings" menu page to the WordPress admin.
     */
    public static function add_admin_menu() {
        add_menu_page('Brand Settings', 'Brand Settings', 'manage_options', 'canna_rewards_settings', [self::class, 'settings_page_html'], 'dashicons-awards', 20);
    }

    /**
     * Registers the setting group, sections, and fields for the settings page.
     */
    public static function settings_init() {
        register_setting('canna_rewards_group', 'canna_rewards_options');
        
        // General Section
        add_settings_section('canna_settings_section_general', 'General Brand Configuration', null, 'canna_rewards_settings');
        add_settings_field('frontend_url', 'PWA Frontend URL', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'frontend_url', 'type' => 'url', 'description' => 'The base URL of your PWA (e.g., https://app.yourdomain.com) for password resets.']);
        add_settings_field('support_email', 'Support Email Address', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'support_email', 'type' => 'email', 'description' => 'Email for all support form submissions.']);
        add_settings_field('welcome_reward_product', 'First Scan Reward Product', [self::class, 'field_select_product_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'welcome_reward_product', 'description' => "Select the product offered for a user's first scan."]);
        add_settings_field('referral_signup_gift', 'Referral Sign-up Gift', [self::class, 'field_select_product_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'referral_signup_gift', 'description' => 'Select the gift for new users who sign up via referral.']);
        add_settings_field('referral_signup_points', 'New User Referral Points', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'referral_signup_points', 'type' => 'number', 'description' => 'Points a new user gets for signing up with a referral code. Default: 50.']);
        add_settings_field('referrer_bonus_points', 'Referrer Bonus Points', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'referrer_bonus_points', 'type' => 'number', 'description' => 'Points the referrer gets after their friend completes their first scan. Default: 200.']);
        add_settings_field('referral_banner_text', 'Referral Banner Text', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'referral_banner_text', 'type' => 'text', 'description' => 'e.g., "🎁 Earn More By Inviting Your Friends"']);
        
        // Theme Section
        add_settings_section('canna_settings_section_theme', 'Theme & Branding Configuration', null, 'canna_rewards_settings');
        add_settings_field('primary_color', 'Primary Color', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'primary_color', 'type' => 'color', 'description' => 'Main brand color for buttons, links, etc.']);
        add_settings_field('secondary_color', 'Secondary/Accent Color', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'secondary_color', 'type' => 'color', 'description' => 'Color for accents and banners.']);
        add_settings_field('primary_font', 'Primary Font (Google Fonts)', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'primary_font', 'type' => 'text', 'description' => 'e.g., "Inter", "Montserrat", "Roboto Mono"']);
    }

    /**
     * Renders a generic HTML input field for a setting.
     * @param array $args Arguments passed from add_settings_field.
     */
    public static function field_html_callback($args) {
        $options = get_option('canna_rewards_options');
        $value = $options[$args['id']] ?? '';
        printf('<input type="%s" id="%s" name="canna_rewards_options[%s]" value="%s" class="regular-text" /><p class="description">%s</p>', esc_attr($args['type']), esc_attr($args['id']), esc_attr($args['id']), esc_attr($value), esc_html($args['description']));
    }

    /**
     * Renders a dropdown select field populated with WooCommerce products.
     * @param array $args Arguments passed from add_settings_field.
     */
    public static function field_select_product_callback($args) {
        $options = get_option('canna_rewards_options');
        $value = $options[$args['id']] ?? '';
        $products = wc_get_products(['status' => 'publish', 'limit' => -1]);
        echo '<select id="' . esc_attr($args['id']) . '" name="canna_rewards_options[' . esc_attr($args['id']) . ']">';
        echo '<option value="">-- Select a Reward --</option>';
        foreach ($products as $product) {
            printf('<option value="%s"%s>%s</option>', esc_attr($product->get_id()), selected($value, $product->get_id(), false), esc_html($product->get_name()));
        }
        echo '</select>';
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }

    /**
     * Renders the HTML for the main settings page container.
     */
    public static function settings_page_html() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('canna_rewards_group');
                do_settings_sections('canna_rewards_settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
}