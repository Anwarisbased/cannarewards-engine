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
        add_action('admin_post_canna_generate_codes', [self::class, 'handle_code_generation']); // Hook for form submission
    }

    /**
     * Adds the top-level "Brand Settings" menu page and sub-pages to the WordPress admin.
     */
    public static function add_admin_menu() {
        add_menu_page('Brand Settings', 'Brand Settings', 'manage_options', 'canna_rewards_settings', [self::class, 'settings_page_html'], 'dashicons-awards', 20);
        add_submenu_page('canna_rewards_settings', 'QR Code Generator', 'QR Code Generator', 'manage_options', 'canna_qr_generator', [self::class, 'qr_generator_page_html']);
    }

    /**
     * Registers the setting group, sections, and fields for the settings page.
     */
    public static function settings_init() {
        register_setting('canna_rewards_group', 'canna_rewards_options');
        
        // General Section
        add_settings_section('canna_settings_section_general', 'General Brand Configuration', null, 'canna_rewards_settings');
        add_settings_field('frontend_url', 'PWA Frontend URL', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'frontend_url', 'type' => 'url', 'description' => 'The base URL of your PWA (e.g., https://app.yourdomain.com) for password resets and QR code links.']);
        add_settings_field('support_email', 'Support Email Address', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'support_email', 'type' => 'email', 'description' => 'Email for all support form submissions.']);
        add_settings_field('welcome_reward_product', 'First Scan Reward Product', [self::class, 'field_select_product_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'welcome_reward_product', 'description' => "Select the product offered for a user's first scan."]);
        add_settings_field('referral_signup_gift', 'Referral Sign-up Gift', [self::class, 'field_select_product_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'referral_signup_gift', 'description' => 'Select the gift for new users who sign up via referral.']);
        add_settings_field('referral_signup_points', 'New User Referral Points', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'referral_signup_points', 'type' => 'number', 'description' => 'Points a new user gets for signing up with a referral code. Default: 50.']);
        add_settings_field('referrer_bonus_points', 'Referrer Bonus Points', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'referrer_bonus_points', 'type' => 'number', 'description' => 'Points the referrer gets after their friend completes their first scan. Default: 200.']);
        add_settings_field('referral_banner_text', 'Referral Banner Text', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_general', ['id' => 'referral_banner_text', 'type' => 'text', 'description' => 'e.g., "🎁 Earn More By Inviting Your Friends"']);
        
        // --- START: NEW ADVANCED THEMING SECTION ---
        add_settings_section('canna_settings_section_theme', 'Advanced Theming (Shadcn)', [self::class, 'theme_section_callback'], 'canna_rewards_settings');

        // Layout & Fonts
        add_settings_field('theme_primary_font', 'Primary Font (Google Fonts)', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'theme_primary_font', 'type' => 'text', 'description' => 'e.g., "Inter", "Montserrat", "Roboto Mono"']);
        add_settings_field('theme_radius', 'Border Radius', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'theme_radius', 'type' => 'text', 'description' => 'Base corner radius for elements. e.g., "0.5rem", "1rem"']);

        // Light Theme Colors
        add_settings_field('theme_background', 'Background (Light)', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'theme_background', 'type' => 'text', 'description' => 'HSL format: 0 0% 100%']);
        add_settings_field('theme_foreground', 'Foreground (Light)', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'theme_foreground', 'type' => 'text', 'description' => 'HSL format: 222.2 84% 4.9%']);
        add_settings_field('theme_card', 'Card (Light)', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'theme_card', 'type' => 'text', 'description' => 'HSL format: 0 0% 100%']);
        add_settings_field('theme_primary', 'Primary (Light)', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'theme_primary', 'type' => 'text', 'description' => 'HSL format: 222.2 47.4% 11.2%']);
        add_settings_field('theme_primary_foreground', 'Primary Foreground (Light)', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'theme_primary_foreground', 'type' => 'text', 'description' => 'HSL format: 210 40% 98%']);
        add_settings_field('theme_secondary', 'Secondary (Light)', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'theme_secondary', 'type' => 'text', 'description' => 'HSL format: 210 40% 96.1%']);
        add_settings_field('theme_destructive', 'Destructive (Light)', [self::class, 'field_html_callback'], 'canna_rewards_settings', 'canna_settings_section_theme', ['id' => 'theme_destructive', 'type' => 'text', 'description' => 'HSL format: 0 84.2% 60.2%']);
        // --- END: NEW ADVANCED THEMING SECTION ---
    }
    
    // --- START: NEW SECTION CALLBACK ---
    public static function theme_section_callback() {
        echo '<p>Control the PWA\'s visual appearance. Use HSL values (e.g., "222.2 47.4% 11.2%") for colors, as defined in <code>globals.css</code>. Leave fields blank to use the PWA\'s default styling.</p>';
    }
    // --- END: NEW SECTION CALLBACK ---

    /**
     * Renders a generic HTML input field for a setting.
     * @param array $args Arguments passed from add_settings_field.
     */
    public static function field_html_callback($args) {
        $options = get_option('canna_rewards_options');
        $value = $options[$args['id']] ?? '';
        printf('<input type="%s" id="%s" name="canna_rewards_options[%s]" value="%s" class="regular-text" placeholder="%s" /><p class="description">%s</p>', esc_attr($args['type']), esc_attr($args['id']), esc_attr($args['id']), esc_attr($value), esc_attr($args['description'] ?? ''), esc_html($args['description'] ?? ''));
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

    /**
     * Renders the HTML for the QR Code Generator page.
     */
    public static function qr_generator_page_html() {
        if (!current_user_can('manage_options')) return;
        $products = wc_get_products(['status' => 'publish', 'limit' => -1]);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>Generate a batch of unique QR codes for a specific product. A CSV file will be downloaded containing the codes and the full URLs for printing.</p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="canna_generate_codes">
                <?php wp_nonce_field('canna_generate_codes_nonce', '_wpnonce'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="product_id">Select Product</label></th>
                            <td>
                                <select id="product_id" name="product_id" required>
                                    <option value="">— Select a Product —</option>
                                    <?php foreach ($products as $product) : ?>
                                        <option value="<?php echo esc_attr($product->get_id()); ?>">
                                            <?php echo esc_html($product->get_name()); ?> (SKU: <?php echo esc_html($product->get_sku()); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Choose the product these codes will be associated with.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="points">Points per Scan</label></th>
                            <td>
                                <input name="points" type="number" id="points" value="100" class="short-text" required />
                                <p class="description">The base number of points awarded for scanning a code from this batch.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="quantity">Quantity to Generate</label></th>
                            <td>
                                <input name="quantity" type="number" id="quantity" value="100" class="short-text" required min="1" max="5000" />
                                <p class="description">How many unique codes to generate (max 5,000 per batch).</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button('Generate Codes and Download CSV'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handles the form submission for generating QR codes and streaming a CSV.
     */
    public static function handle_code_generation() {
        if (!current_user_can('manage_options') || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'canna_generate_codes_nonce')) {
            wp_die('You are not authorized to perform this action.');
        }

        global $wpdb;
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $points = isset($_POST['points']) ? absint($_POST['points']) : 100;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 0;

        if ($quantity <= 0 || $quantity > 5000 || $product_id <= 0) {
            wp_die('Invalid input. Please check quantity and product selection.');
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_die('Invalid product selected.');
        }

        $sku = $product->get_sku() ?: 'NOSKU';
        $batch_id = uniqid('batch_' . sanitize_key($sku) . '_');
        $table_name = $wpdb->prefix . 'canna_reward_codes';

        // --- FIX: Use the saved setting for the frontend URL ---
        $options = get_option('canna_rewards_options', []);
        $frontend_url = !empty($options['frontend_url']) ? rtrim($options['frontend_url'], '/') : home_url();
        if (empty($frontend_url)) {
            wp_die('PWA Frontend URL is not set in Brand Settings. Please configure it before generating codes.');
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cannarewards-codes-' . $batch_id . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['unique_code', 'full_url']);

        for ($i = 0; $i < $quantity; $i++) {
            $unique_part = bin2hex(random_bytes(8)); // 16 characters
            $new_code = strtoupper($sku) . '-' . $unique_part;
            
            $wpdb->insert($table_name, [
                'code' => $new_code,
                'points' => $points,
                'sku' => $sku,
                'batch_id' => $batch_id,
                'is_used' => 0
            ]);
            
            $full_url = $frontend_url . '/claim?code=' . urlencode($new_code);
            fputcsv($output, [$new_code, $full_url]);
        }
        
        fclose($output);
        exit;
    }
}