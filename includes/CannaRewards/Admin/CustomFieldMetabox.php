<?php
namespace CannaRewards\Admin;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Defines the metabox for the CannaRewards Custom Field CPT.
 */
class CustomFieldMetabox {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_canna_custom_field', [$this, 'save_data']);
    }

    public function add_metabox() {
        add_meta_box(
            'canna_custom_field_details',
            __('Field Configuration', 'canna-rewards'),
            [$this, 'render_metabox'],
            'canna_custom_field',
            'normal',
            'high'
        );
    }

    public function render_metabox($post) {
        wp_nonce_field('canna_save_custom_field_data', 'canna_custom_field_nonce');
        
        $meta_key = get_post_meta($post->ID, 'meta_key', true);
        $field_type = get_post_meta($post->ID, 'field_type', true);
        $options = get_post_meta($post->ID, 'options', true);
        $display_location = (array) get_post_meta($post->ID, 'display_location', true);

        ?>
        <p>The <strong>Field Label</strong> is the post title above. This will be shown to the user in the PWA.</p>
        <hr>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="meta_key">Meta Key</label></th>
                    <td>
                        <input type="text" id="meta_key" name="meta_key" value="<?php echo esc_attr($meta_key); ?>" class="regular-text" required />
                        <p class="description">The machine-readable key saved in the database (e.g., favorite_strain_type). Should be lowercase with underscores.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="field_type">Field Type</label></th>
                    <td>
                        <select id="field_type" name="field_type">
                            <option value="text" <?php selected($field_type, 'text'); ?>>Text</option>
                            <option value="date" <?php selected($field_type, 'date'); ?>>Date</option>
                            <option value="dropdown" <?php selected($field_type, 'dropdown'); ?>>Dropdown</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="options">Options</label></th>
                    <td>
                        <textarea id="options" name="options" rows="3" class="large-text" placeholder="Red&#x0a;Green&#x0a;Blue"><?php echo esc_textarea($options); ?></textarea>
                        <p class="description">For "Dropdown" type only. Enter one option per line.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Display Location</label></th>
                    <td>
                        <fieldset>
                            <label><input type="checkbox" name="display_location[]" value="edit_profile" <?php checked(in_array('edit_profile', $display_location, true)); ?>> Edit Profile Modal</label><br>
                            <label><input type="checkbox" name="display_location[]" value="registration" <?php checked(in_array('registration', $display_location, true)); ?>> Registration Form</label>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function save_data($post_id) {
        if (!isset($_POST['canna_custom_field_nonce']) || !wp_verify_nonce($_POST['canna_custom_field_nonce'], 'canna_save_custom_field_data')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (get_post_type($post_id) !== 'canna_custom_field' || !current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta($post_id, 'meta_key', isset($_POST['meta_key']) ? sanitize_key($_POST['meta_key']) : '');
        update_post_meta($post_id, 'field_type', isset($_POST['field_type']) ? sanitize_text_field($_POST['field_type']) : 'text');
        update_post_meta($post_id, 'options', isset($_POST['options']) ? sanitize_textarea_field($_POST['options']) : '');
        update_post_meta($post_id, 'display_location', isset($_POST['display_location']) ? array_map('sanitize_text_field', (array) $_POST['display_location']) : []);
    }
}