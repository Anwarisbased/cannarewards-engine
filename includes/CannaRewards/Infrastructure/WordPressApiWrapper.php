<?php
namespace CannaRewards\Infrastructure;

use WP_Query;
use WP_User;
use WC_Product;
use WC_Order;
use WP_Error;

/**
 * The single gateway to the global WordPress environment. This is the only
 * class in the application that is allowed to call global WordPress/WooCommerce
 * functions directly. This isolates our domain logic for pure testability.
 */
final class WordPressApiWrapper {
    private \wpdb $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Safely exposes the database prefix to other parts of the application.
     */
    public function getDbPrefix(): string {
        return $this->db->prefix;
    }

    // --- User & Meta Functions ---

    public function getUserMeta(int $userId, string $key, bool $single = true) {
        return get_user_meta($userId, $key, $single);
    }

    public function updateUserMeta(int $userId, string $key, $value): void {
        update_user_meta($userId, $key, $value);
    }
    
    public function getPostMeta(int $postId, string $key, bool $single = true) {
        return get_post_meta($postId, $key, $single);
    }
    
    public function getUserById(int $userId): ?WP_User {
        $user = get_userdata($userId);
        return $user ?: null;
    }

    public function findUserBy(string $field, string $value): ?WP_User {
        $user = get_user_by($field, $value);
        return $user ?: null;
    }

    /** @return WP_User[] */
    public function findUsers(array $args): array {
        return get_users($args);
    }

    /**
     * Wraps the global wp_insert_user function.
     * @param array $userData The user data array.
     * @return int|\WP_Error The new user's ID on success, or a WP_Error object on failure.
     */
    public function createUser(array $userData): int|\WP_Error {
        return wp_insert_user($userData);
    }

    /**
     * Wraps the global wp_update_user function.
     * @param array $userData The user data array.
     * @return int|\WP_Error The updated user's ID on success, or a WP_Error object on failure.
     */
    public function updateUser(array $userData): int|\WP_Error {
        return wp_update_user($userData);
    }

    // --- Post & Query Functions ---

    /** @return \WP_Post[] */
    public function getPosts(array $args): array {
        $query = new WP_Query($args);
        wp_reset_postdata();
        return $query->posts;
    }

    public function getPageByPath(string $path, string $output = OBJECT, string $post_type = 'page'): ?\WP_Post {
        return get_page_by_path($path, $output, $post_type);
    }

    public function applyFilters(string $tag, string $value) {
        // This wrapper is essential for making services that use filters testable.
        return apply_filters($tag, $value);
    }

    // --- Options & Transients ---

    public function getOption(string $key, $default = false) {
        return get_option($key, $default);
    }
    
    public function getTransient(string $key) {
        return get_transient($key);
    }

    public function setTransient(string $key, $value, int $expiration): void {
        set_transient($key, $value, $expiration);
    }
    
    public function deleteTransient(string $key): bool {
        return delete_transient($key);
    }

    // --- WooCommerce Functions ---

    /** @return \WC_Product[] */
    public function getProducts(array $args): array {
        if (!function_exists('wc_get_products')) {
            return [];
        }
        return wc_get_products($args);
    }
    
    /** @return \WC_Order[] */
    public function getOrders(array $args): array {
        if (!function_exists('wc_get_orders')) {
            return [];
        }
        return wc_get_orders($args);
    }

    public function getProductIdBySku(string $sku): int {
        return (int) wc_get_product_id_by_sku($sku);
    }

    public function getProduct(int $productId): ?WC_Product {
        return wc_get_product($productId);
    }

    /** @return WC_Order|WP_Error */
    public function createOrder(array $args) {
        return wc_create_order($args);
    }

    // --- WordPress Core Functions ---

    public function isEmail(string $email): bool {
        return is_email($email);
    }

    public function emailExists(string $email): bool {
        return (bool) email_exists($email);
    }
    
    public function getPasswordResetKey(\WP_User $user): string|\WP_Error {
        return get_password_reset_key($user);
    }
    
    public function sendMail(string $to, string $subject, string $body): bool {
        return wp_mail($to, $subject, $body);
    }
    
    public function checkPasswordResetKey(string $key, string $login): \WP_User|\WP_Error {
        return check_password_reset_key($key, $login);
    }

    public function resetPassword(\WP_User $user, string $new_pass): void {
        reset_password($user, $new_pass);
    }

    public function generatePassword(int $length, bool $special_chars, bool $extra_special_chars): string {
        return wp_generate_password($length, $special_chars, $extra_special_chars);
    }

    // --- Database Functions ---

    public function dbGetRow(string $query) {
        return $this->db->get_row($query);
    }

    public function dbGetCol(string $query) {
        return $this->db->get_col($query);
    }

    public function dbGetVar(string $query) {
        return $this->db->get_var($query);
    }
    
    public function dbGetResults(string $query) {
        return $this->db->get_results($query);
    }

    public function dbInsert(string $table, array $data, array $format = null) {
        return $this->db->insert($this->db->prefix . $table, $data, $format);
    }
    
    public function dbUpdate(string $table, array $data, array $where, array $format = null, array $where_format = null) {
        return $this->db->update($this->db->prefix . $table, $data, $where, $format, $where_format);
    }

    public function dbPrepare(string $query, ...$args) {
        return $this->db->prepare($query, ...$args);
    }
    
    public function getAttachmentImageUrl(int $attachmentId, string $size = 'thumbnail'): string {
        return wp_get_attachment_image_url($attachmentId, $size);
    }
    
    public function getPlaceholderImageSrc(): string {
        return wc_placeholder_img_src();
    }
    
    // --- Additional WordPress Functions ---
    
    public function getTheTitle(int $postId): string {
        return get_the_title($postId);
    }
    
    public function getPost(int $postId): ?\WP_Post {
        return get_post($postId);
    }
    
    public function restDoRequest(\WP_REST_Request $request): \WP_REST_Response {
        return rest_do_request($request);
    }
    
    public function isWpError($thing): bool {
        return is_wp_error($thing);
    }
    
    public function homeUrl(): string {
        return home_url();
    }
}