<?php
namespace CannaRewards\Admin;

// ARCHITECTURAL NOTE: This class exists within the Admin boundary.
// Direct calls to WordPress functions (e.g., get_post_meta, add_meta_box)
// are permitted here for pragmatic integration with the WordPress admin UI.
// This contrasts with the core application logic in Services/Repositories,
// which must remain pure.

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Defines the metabox for the CannaRewards Achievement Custom Post Type.
 * Now includes a JavaScript-powered rule builder UI.
 */
class AchievementMetabox {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_achievement_metabox']);
        add_action('save_post_canna_achievement', [$this, 'save_metabox_data']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    // We need to enqueue a dummy script to use wp_localize_script
    public function enqueue_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        wp_enqueue_script('canna-rule-builder', plugin_dir_url(CANNA_PLUGIN_FILE) . 'assets/js/noop.js', [], '1.0.0', true);
    }

    public function add_achievement_metabox() {
        add_meta_box(
            'canna_achievement_details',
            __('Achievement Details & Rules', 'canna-rewards'),
            [$this, 'render_metabox'],
            'canna_achievement',
            'normal',
            'high'
        );
    }

    public function render_metabox($post) {
        wp_nonce_field('canna_save_achievement_metabox_data', 'canna_achievement_metabox_nonce');

        // Get existing data
        $points_reward   = get_post_meta($post->ID, 'points_reward', true);
        $rarity          = get_post_meta($post->ID, 'rarity', true);
        $trigger_event   = get_post_meta($post->ID, 'trigger_event', true);
        $trigger_count   = get_post_meta($post->ID, 'trigger_count', true) ?: 1;
        $conditions_json = get_post_meta($post->ID, 'conditions', true) ?: '[]';
        
        // Pass data to our JavaScript rule builder
        wp_localize_script('canna-rule-builder', 'cannaRuleBuilderSettings', [
            'apiUrl' => esc_url_raw(rest_url('rewards/v2/rules/conditions')),
            'nonce' => wp_create_nonce('wp_rest'),
            'savedConditions' => json_decode($conditions_json)
        ]);

        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="points_reward"><?php _e('Points Reward', 'canna-rewards'); ?></label></th>
                    <td><input type="number" id="points_reward" name="points_reward" value="<?php echo esc_attr($points_reward); ?>" class="small-text" min="0" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="rarity"><?php _e('Rarity', 'canna-rewards'); ?></label></th>
                    <td>
                        <select id="rarity" name="rarity">
                            <option value="common" <?php selected($rarity, 'common'); ?>>Common</option>
                            <option value="uncommon" <?php selected($rarity, 'uncommon'); ?>>Uncommon</option>
                            <option value="rare" <?php selected($rarity, 'rare'); ?>>Rare</option>
                            <option value="epic" <?php selected($rarity, 'epic'); ?>>Epic</option>
                            <option value="legendary" <?php selected($rarity, 'legendary'); ?>>Legendary</option>
                        </select>
                    </td>
                </tr>
                <tr style="border-top: 1px solid #ddd;">
                    <th scope="row" colspan="2"><h4><?php _e('Rule Engine', 'canna-rewards'); ?></h4></th>
                </tr>
                <tr>
                    <th scope="row"><label for="trigger_event"><?php _e('Triggered When', 'canna-rewards'); ?></label></th>
                    <td>
                        <select id="trigger_event" name="trigger_event" style="width: 200px;">
                            <option value="product_scanned" <?php selected($trigger_event, 'product_scanned'); ?>>Product Scanned</option>
                            <option value="reward_redeemed" <?php selected($trigger_event, 'reward_redeemed'); ?>>Reward Redeemed</option>
                            <option value="user_rank_changed" <?php selected($trigger_event, 'user_rank_changed'); ?>>User Rank Changed</option>
                        </select>
                        <input type="number" id="trigger_count" name="trigger_count" value="<?php echo esc_attr($trigger_count); ?>" class="small-text" min="1" />
                        <label for="trigger_count">time(s)</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Conditions', 'canna-rewards'); ?></th>
                    <td>
                        <div id="rule-builder-container"></div>
                        <button type="button" class="button" id="add-rule-btn"><?php _e('+ Add Condition', 'canna-rewards'); ?></button>
                        <p class="description">All conditions must be true for the achievement to be awarded.</p>
                        <input type="hidden" name="conditions" id="conditions-hidden-input" />
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Rule Row Template -->
        <template id="rule-row-template">
            <div class="rule-row" style="margin-bottom: 10px; display: flex; gap: 5px; align-items: center;">
                <select class="rule-field" style="width: 200px;"></select>
                <select class="rule-operator" style="width: 150px;"></select>
                <div class="rule-value-wrapper" style="display: inline-block;"></div>
                <button type="button" class="button button-link-delete remove-rule-btn">&times;</button>
            </div>
        </template>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- CONFIG ---
            const container = document.getElementById('rule-builder-container');
            const addBtn = document.getElementById('add-rule-btn');
            const template = document.getElementById('rule-row-template');
            const form = document.querySelector('form#post');
            const hiddenInput = document.getElementById('conditions-hidden-input');
            let availableConditions = [];

            // --- INITIALIZATION ---
            async function init() {
                try {
                    const response = await fetch(cannaRuleBuilderSettings.apiUrl, {
                        headers: { 'X-WP-Nonce': cannaRuleBuilderSettings.nonce }
                    });
                    if (!response.ok) throw new Error('Failed to fetch rule conditions.');
                    
                    availableConditions = await response.json();
                    
                    // Render existing saved conditions
                    cannaRuleBuilderSettings.savedConditions.forEach(condition => addRuleRow(condition));

                } catch (error) {
                    container.innerHTML = `<p style="color: red;"><strong>Error:</strong> Could not load rule builder. ${error.message}</p>`;
                    console.error(error);
                }
            }

            // --- UI FUNCTIONS ---
            function addRuleRow(condition = {}) {
                const clone = template.content.cloneNode(true);
                const row = clone.querySelector('.rule-row');
                const fieldSelect = clone.querySelector('.rule-field');
                const operatorSelect = clone.querySelector('.rule-operator');
                const valueWrapper = clone.querySelector('.rule-value-wrapper');

                // Populate Field dropdown
                availableConditions.forEach(opt => {
                    fieldSelect.add(new Option(opt.label, opt.key));
                });

                // Event listener to update operator and value when field changes
                fieldSelect.addEventListener('change', () => updateRow(row, fieldSelect.value));
                
                // Set initial value and trigger change to populate the rest
                if (condition.field) {
                    fieldSelect.value = condition.field;
                }
                updateRow(row, fieldSelect.value, condition);

                container.appendChild(clone);
            }

            function updateRow(row, selectedFieldKey, existingCondition = {}) {
                const operatorSelect = row.querySelector('.rule-operator');
                const valueWrapper = row.querySelector('.rule-value-wrapper');
                const selectedCondition = availableConditions.find(c => c.key === selectedFieldKey);

                // Update operators
                operatorSelect.innerHTML = '';
                selectedCondition.operators.forEach(op => operatorSelect.add(new Option(op, op)));
                if (existingCondition.operator) {
                    operatorSelect.value = existingCondition.operator;
                }

                // Update value input
                valueWrapper.innerHTML = '';
                let valueInput;
                if (selectedCondition.inputType === 'select') {
                    valueInput = document.createElement('select');
                    valueInput.style.width = '200px';
                    const options = Array.isArray(selectedCondition.options) 
                        ? selectedCondition.options.map(o => ({ value: o, text: o }))
                        : Object.entries(selectedCondition.options).map(([val, txt]) => ({ value: val, text: txt }));
                    
                    options.forEach(opt => valueInput.add(new Option(opt.text, opt.value)));

                } else {
                    valueInput = document.createElement('input');
                    valueInput.type = selectedCondition.inputType;
                    valueInput.style.width = '194px';
                }
                valueInput.className = 'rule-value';
                if (existingCondition.value) {
                    valueInput.value = existingCondition.value;
                }
                valueWrapper.appendChild(valueInput);
            }

            // --- EVENT LISTENERS ---
            addBtn.addEventListener('click', () => addRuleRow());

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-rule-btn')) {
                    e.target.closest('.rule-row').remove();
                }
            });

            form.addEventListener('submit', function() {
                const conditions = [];
                container.querySelectorAll('.rule-row').forEach(row => {
                    conditions.push({
                        field: row.querySelector('.rule-field').value,
                        operator: row.querySelector('.rule-operator').value,
                        value: row.querySelector('.rule-value').value
                    });
                });
                hiddenInput.value = JSON.stringify(conditions);
            });

            init();
        });
        </script>
        <?php
    }

    public function save_metabox_data($post_id) {
        if (!isset($_POST['canna_achievement_metabox_nonce']) || !wp_verify_nonce($_POST['canna_achievement_metabox_nonce'], 'canna_save_achievement_metabox_data')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // --- THIS IS THE FIX ---
        // We now get the JSON from our hidden input, which is populated by the JavaScript.
        // We also no longer save `achievement_key` or `is_active` as they are not in the new form.
        // These can be moved to their own dedicated metaboxes if needed.
        update_post_meta($post_id, 'points_reward', isset($_POST['points_reward']) ? absint($_POST['points_reward']) : 0);
        update_post_meta($post_id, 'rarity', isset($_POST['rarity']) ? sanitize_text_field($_POST['rarity']) : 'common');
        update_post_meta($post_id, 'trigger_event', isset($_POST['trigger_event']) ? sanitize_text_field($_POST['trigger_event']) : '');
        update_post_meta($post_id, 'trigger_count', isset($_POST['trigger_count']) ? absint($_POST['trigger_count']) : 1);

        if (isset($_POST['conditions'])) {
            $conditions_json = wp_unslash($_POST['conditions']);
            // Basic JSON validation before saving
            if (json_decode($conditions_json) !== null) {
                update_post_meta($post_id, 'conditions', $conditions_json);
            }
        }
    }
}