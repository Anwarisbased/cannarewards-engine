<?php
namespace CannaRewards\Admin;

// ARCHITECTURAL NOTE: This class exists within the Admin boundary.
// Direct calls to WordPress functions (e.g., get_post_meta, add_meta_box)
// are permitted here for pragmatic integration with the WordPress admin UI.
// This contrasts with the core application logic in Services/Repositories,
// which must remain pure.

use CannaRewards\Infrastructure\WordPressApiWrapper;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles the Brand Settings admin menu page.
 */
final class AdminMenu {
    const PARENT_SLUG = 'canna_rewards_settings';
    private WordPressApiWrapper $wp;
    private FieldFactory $fieldFactory;

    public function __construct(WordPressApiWrapper $wp, FieldFactory $fieldFactory) {
        $this->wp = $wp;
        $this->fieldFactory = $fieldFactory;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings_init']);
        add_action('admin_post_canna_generate_codes', [$this, 'handle_code_generation']);
    }

    public function add_admin_menu(): void {
        add_menu_page('Brand Settings', 'Brand Settings', 'manage_options', self::PARENT_SLUG, [$this, 'settings_page_html'], 'dashicons-store', 20);
        add_submenu_page(self::PARENT_SLUG, 'Brand Settings', 'Brand Settings', 'manage_options', self::PARENT_SLUG, [$this, 'settings_page_html']);
        add_submenu_page(self::PARENT_SLUG, 'QR Code Generator', 'QR Code Generator', 'manage_options', 'canna_qr_generator', [$this, 'qr_generator_page_html']);
    }

    public function settings_init(): void {
        register_setting('canna_rewards_group', 'canna_rewards_options');
        
        // Sections and fields setup remains the same...
        add_settings_section('canna_settings_section_general', 'General Brand Configuration', null, self::PARENT_SLUG);
        add_settings_field('frontend_url', 'PWA Frontend URL', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_general', ['id' => 'frontend_url', 'type' => 'url', 'description' => 'The base URL of your PWA for password resets and QR code links.']);
        add_settings_field('support_email', 'Support Email Address', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_general', ['id' => 'support_email', 'type' => 'email', 'description' => 'Email for all support form submissions.']);
        add_settings_field('welcome_reward_product', 'First Scan Reward Product', [$this, 'field_select_product_callback'], self::PARENT_SLUG, 'canna_settings_section_general', ['id' => 'welcome_reward_product', 'description' => "Select the product offered for a user's first scan."]);
        add_settings_field('referral_signup_gift', 'Referral Sign-up Gift', [$this, 'field_select_product_callback'], self::PARENT_SLUG, 'canna_settings_section_general', ['id' => 'referral_signup_gift', 'description' => 'Select the gift for new users who sign up via referral.']);
        add_settings_field('referral_banner_text', 'Referral Banner Text', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_general', ['id' => 'referral_banner_text', 'type' => 'text', 'description' => 'e.g., "🎁 Earn More By Inviting Your Friends"']);
        
        add_settings_section('canna_settings_section_personality', 'Brand Personality Engine', [$this, 'personality_section_callback'], self::PARENT_SLUG);
        add_settings_field('points_name', 'Name for "Points"', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_personality', ['id' => 'points_name', 'type' => 'text', 'placeholder' => 'Points', 'description' => 'What do you call your loyalty currency? e.g., Buds, Tokens, Karma.']);
        add_settings_field('rank_name', 'Name for "Rank"', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_personality', ['id' => 'rank_name', 'type' => 'text', 'placeholder' => 'Rank', 'description' => 'What do you call your loyalty tiers? e.g., Status, Level, Tier.']);
        add_settings_field('welcome_header', 'Welcome Header Text', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_personality', ['id' => 'welcome_header', 'type' => 'text', 'placeholder' => 'Welcome, {firstName}', 'description' => 'Personalize the dashboard greeting. Use {firstName} as a placeholder.']);
        add_settings_field('scan_cta', 'Scan Button CTA', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_personality', ['id' => 'scan_cta', 'type' => 'text', 'placeholder' => 'Scan Product', 'description' => 'The primary call-to-action text on the scan button.']);
        
        add_settings_section('canna_settings_section_theme', 'Advanced Theming (Shadcn)', [$this, 'theme_section_callback'], self::PARENT_SLUG);
        add_settings_field('theme_primary_font', 'Primary Font (Google Fonts)', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_theme', ['id' => 'theme_primary_font', 'type' => 'text', 'description' => 'e.g., "Inter", "Montserrat", "Roboto Mono"']);
        add_settings_field('theme_radius', 'Border Radius', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_theme', ['id' => 'theme_radius', 'type' => 'text', 'description' => 'Base corner radius for elements. e.g., "0.5rem", "1rem"']);
        add_settings_field('theme_background', 'Background (Light)', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_theme', ['id' => 'theme_background', 'type' => 'text', 'description' => 'HSL format: 0 0% 100%']);
        add_settings_field('theme_foreground', 'Foreground (Light)', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_theme', ['id' => 'theme_foreground', 'type' => 'text', 'description' => 'HSL format: 222.2 84% 4.9%']);
        add_settings_field('theme_card', 'Card (Light)', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_theme', ['id' => 'theme_card', 'type' => 'text', 'description' => 'HSL format: 0 0% 100%']);
        add_settings_field('theme_primary', 'Primary (Light)', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_theme', ['id' => 'theme_primary', 'type' => 'text', 'description' => 'HSL format: 222.2 47.4% 11.2%']);
        add_settings_field('theme_primary_foreground', 'Primary Foreground (Light)', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_theme', ['id' => 'theme_primary_foreground', 'type' => 'text', 'description' => 'HSL format: 210 40% 98%']);
        add_settings_field('theme_secondary', 'Secondary (Light)', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_theme', ['id' => 'theme_secondary', 'type' => 'text', 'description' => 'HSL format: 210 40% 96.1%']);
        add_settings_field('theme_destructive', 'Destructive (Light)', [$this, 'field_html_callback'], self::PARENT_SLUG, 'canna_settings_section_theme', ['id' => 'theme_destructive', 'type' => 'text', 'description' => 'HSL format: 0 84.2% 60.2%']);
    }

    public function personality_section_callback(): void { 
        echo '<p>Define the core language and feel of your rewards program to match your brand\'s voice.</p>'; 
    }
    
    public function theme_section_callback(): void { 
        echo '<p>Control the PWA\'s visual appearance. Use HSL values (e.g., "222.2 47.4% 11.2%") for colors, as defined in <code>globals.css</code>. Leave fields blank to use the PWA\'s default styling.</p>'; 
    }

    public function field_html_callback($args): void {
        $options = $this->wp->getOption('canna_rewards_options');
        $value = $options[$args['id']] ?? '';
        $this->fieldFactory->render_text_input(
            "canna_rewards_options[{$args['id']}]",
            $value,
            $args
        );
    }

    public function field_select_product_callback($args): void {
        if (!function_exists('wc_get_products')) { 
            echo '<p>WooCommerce is not active.</p>'; 
            return; 
        }
        $options = $this->wp->getOption('canna_rewards_options');
        $value = $options[$args['id']] ?? '';
        // REFACTOR: Use the wrapper. No exceptions.
        $products = $this->wp->getProducts(['status' => 'publish', 'limit' => -1]);
        
        $product_options = ['' => '-- Select a Reward --'];
        foreach ($products as $product) {
            $product_options[$product->get_id()] = $product->get_name();
        }
        
        $this->fieldFactory->render_select(
            "canna_rewards_options[{$args['id']}]",
            $value,
            $product_options,
            $args
        );
    }

    public function settings_page_html(): void {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1><form action="options.php" method="post">';
        settings_fields('canna_rewards_group');
        do_settings_sections(self::PARENT_SLUG);
        submit_button('Save Settings');
        echo '</form></div>';
    }

    public function qr_generator_page_html(): void {
        if (!current_user_can('manage_options')) { return; }
        if (!function_exists('wc_get_products')) { 
            echo '<div class="wrap"><h1>QR Code Generator</h1><p>WooCommerce must be active to use this feature.</p></div>'; 
            return; 
        }
        $products = $this->wp->getProducts(['status' => 'publish', 'limit' => -1]);
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
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="quantity">Quantity to Generate</label></th>
                            <td><input name="quantity" type="number" id="quantity" value="100" class="short-text" required min="1" max="10000" /></td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button('Generate Codes and Download CSV'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_code_generation(): void {
        if (!current_user_can('manage_options') || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'canna_generate_codes_nonce')) {
            wp_die('You are not authorized to perform this action.');
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 0;
        if ($quantity <= 0 || $quantity > 10000 || $product_id <= 0) { wp_die('Invalid input.'); }

        $product = $this->wp->getProduct($product_id);
        if (!$product) { wp_die('Invalid product selected.'); }

        $sku = $product->get_sku() ?: 'NOSKU';
        $batch_id = uniqid('batch_' . sanitize_key($sku) . '_');
        
        $options = $this->wp->getOption('canna_rewards_options', []);
        $frontend_url = !empty($options['frontend_url']) ? rtrim($options['frontend_url'], '/') : home_url();
        if (empty($frontend_url)) { wp_die('PWA Frontend URL is not set in Brand Settings.'); }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cannarewards-codes-' . $batch_id . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['unique_code', 'full_url']);

        for ($i = 0; $i < $quantity; $i++) {
            $unique_part = bin2hex(random_bytes(8));
            $new_code = strtoupper($sku) . '-' . $unique_part;
            
            $this->wp->dbInsert('canna_reward_codes', [
                'code' => $new_code, 'sku' => $sku, 'batch_id' => $batch_id, 'is_used' => 0
            ]);
            
            $full_url = $frontend_url . '/claim?code=' . urlencode($new_code);
            fputcsv($output, [$new_code, $full_url]);
        }
        
        fclose($output);
        exit;
    }
}