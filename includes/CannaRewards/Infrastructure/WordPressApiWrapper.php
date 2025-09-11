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

    // --- WooCommerce Functions ---

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
}