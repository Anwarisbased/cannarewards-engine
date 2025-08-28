<?php
/**
 * Defines the metabox for the CannaRewards Achievement Custom Post Type.
 *
 * This class handles the creation and saving of custom fields for achievements,
 * including the trigger event, type, points reward, rarity, icon URL, active status,
 * and the dynamic rule builder.
 *
 * @package CannaRewards
 * @subpackage Admin
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

class Canna_Achievement_Metabox {

    /**
     * Constructor.
     * Registers hooks for adding the metabox and saving its data.
     */
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_achievement_metabox']);
        add_action('save_post_canna_achievement', [$this, 'save_achievement_metabox_data']);
    }

    /**
     * Adds the custom metabox to the Achievement CPT edit screen.
     */
    public function add_achievement_metabox() {
        add_meta_box(
            'canna_achievement_details',
            __('Achievement Details & Rules', 'canna-rewards'),
            [$this, 'render_achievement_metabox'],
            'canna_achievement',
            'normal',
            'high'
        );
    }

    /**
     * Defines the available subjects for the rule builder dropdown.
     *
     * @return array
     */
    private static function get_subject_options() {
        return [
            __('User Action Log', 'canna-rewards') => [
                'log_total_scans' => __('Log: Total Scans (All Time)', 'canna-rewards'),
                'log_total_redeems' => __('Log: Total Redemptions (All Time)', 'canna-rewards'),
            ],
            __('User Data', 'canna-rewards') => [
                'user_rank' => __('User: Current Rank', 'canna-rewards'),
                'user_lifetime_points' => __('User: Lifetime Points', 'canna-rewards'),
            ],
            __('Product PIM Data (From Event)', 'canna-rewards') => [
                'product_attribute_strain' => __('PIM: Strain Type', 'canna-rewards'),
                'product_attribute_form' => __('PIM: Product Form', 'canna-rewards'),
                'product_attribute_weight' => __('PIM: Weight', 'canna-rewards'),
            ],
        ];
    }

    /**
     * Defines the available operators for the rule builder dropdown.
     *
     * @return array
     */
    private static function get_operator_options() {
        return [
            '==' => __('Is Equal To', 'canna-rewards'),
            '!=' => __('Is Not Equal To', 'canna-rewards'),
            '>=' => __('Is Greater Than or Equal To', 'canna-rewards'),
            '<=' => __('Is Less Than or Equal To', 'canna-rewards'),
        ];
    }


    /**
     * Renders the HTML for the achievement details metabox.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_achievement_metabox($post) {
        wp_nonce_field('canna_save_achievement_metabox_data', 'canna_achievement_metabox_nonce');

        // Retrieve existing meta values
        $achievement_key = get_post_meta($post->ID, 'achievement_key', true);
        $trigger_type    = get_post_meta($post->ID, 'trigger_type', true);
        $type            = get_post_meta($post->ID, 'type', true);
        $points_reward   = get_post_meta($post->ID, 'points_reward', true);
        $rarity          = get_post_meta($post->ID, 'rarity', true);
        $icon_url        = get_post_meta($post->ID, 'icon_url', true);
        $is_active       = get_post_meta($post->ID, 'is_active', true);
        $rule_groups     = get_post_meta($post->ID, 'rule_groups', true);

        if ($is_active === '') $is_active = 1;

        ?>
        <style>
            .canna-rule-group { border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 20px; background: #fdfdfd; }
            .canna-rule-group-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
            .canna-rule-condition { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
            .canna-rule-condition select, .canna-rule-condition input { flex: 1; }
        </style>
        <table class="form-table">
            <tbody>
                <!-- Standard Achievement Fields -->
                <tr>
                    <th scope="row"><label for="achievement_key"><?php _e('Achievement Key', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="text" id="achievement_key" name="achievement_key" value="<?php echo esc_attr($achievement_key); ?>" class="regular-text" placeholder="<?php _e('Unique identifier (e.g., first_scan)', 'canna-rewards'); ?>" required />
                        <p class="description"><?php _e('A unique, machine-readable key. Cannot be changed after creation.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="trigger_type"><?php _e('Trigger Event', 'canna-rewards'); ?></label></th>
                    <td>
                        <select id="trigger_type" name="trigger_type">
                            <option value="scan" <?php selected($trigger_type, 'scan'); ?>><?php _e('On Product Scan', 'canna-rewards'); ?></option>
                            <option value="redeem" <?php selected($trigger_type, 'redeem'); ?>><?php _e('On Reward Redemption', 'canna-rewards'); ?></option>
                            <option value="profile_update" <?php selected($trigger_type, 'profile_update'); ?>><?php _e('On Profile Update', 'canna-rewards'); ?></option>
                        </select>
                        <p class="description"><?php _e('This determines WHEN the rules for this achievement will be checked.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
                 <!-- Other fields like Type, Points, Rarity, etc. -->
                <tr>
                    <th scope="row"><label for="type"><?php _e('Type', 'canna-rewards'); ?></label></th>
                    <td>
                        <select id="type" name="type">
                            <option value="general" <?php selected($type, 'general'); ?>><?php _e('General', 'canna-rewards'); ?></option>
                            <option value="onboarding" <?php selected($type, 'onboarding'); ?>><?php _e('Onboarding / Quest', 'canna-rewards'); ?></option>
                        </select>
                         <p class="description"><?php _e('Categorizes the achievement for filtering.', 'canna-rewards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="points_reward"><?php _e('Points Reward', 'canna-rewards'); ?></label></th>
                    <td>
                        <input type="number" id="points_reward" name="points_reward" value="<?php echo esc_attr($points_reward); ?>" class="small-text" min="0" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rarity"><?php _e('Rarity', 'canna-rewards'); ?></label></th>
                    <td>
                        <select id="rarity" name="rarity">
                            <option value="common" <?php selected($rarity, 'common'); ?>><?php _e('Common', 'canna-rewards'); ?></option>
                            <option value="uncommon" <?php selected($rarity, 'uncommon'); ?>><?php _e('Uncommon', 'canna-rewards'); ?></option>
                            <option value="rare" <?php selected($rarity, 'rare'); ?>><?php _e('Rare', 'canna-rewards'); ?></option>
                            <option value="epic" <?php selected($rarity, 'epic'); ?>><?php _e('Epic', 'canna-rewards'); ?></option>
                            <option value="legendary" <?php selected($rarity, 'legendary'); ?>><?php _e('Legendary', 'canna-rewards'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="icon_url"><?php _e('Icon URL', 'canna-rewards'); ?></label></th>
                    <td><input type="url" id="icon_url" name="icon_url" value="<?php echo esc_attr($icon_url); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="is_active"><?php _e('Is Active?', 'canna-rewards'); ?></label></th>
                    <td><input type="checkbox" id="is_active" name="is_active" value="1" <?php checked($is_active, 1); ?> /></td>
                </tr>
            </tbody>
        </table>
        
        <hr>
        <h2><?php _e('Achievement Rules', 'canna-rewards'); ?></h2>
        <p class="description"><?php _e('Define the rules that will automatically award this achievement. Multiple groups are treated as "OR". All conditions within a group are treated as "AND". If no rules are added, the achievement is awarded immediately on trigger.', 'canna-rewards'); ?></p>
        
        <div id="rule-builder-container">
            <?php if (!empty($rule_groups) && is_array($rule_groups)) : ?>
                <?php foreach ($rule_groups as $group_index => $conditions) : ?>
                    <div class="canna-rule-group">
                        <div class="canna-rule-group-header">
                            <h4><?php printf(__('Condition Group #%d (All must be true)', 'canna-rewards'), $group_index + 1); ?></h4>
                            <button type="button" class="button button-secondary remove-group-btn"><?php _e('Remove Group', 'canna-rewards'); ?></button>
                        </div>
                        <div class="conditions-container">
                            <?php if (!empty($conditions) && is_array($conditions)) : ?>
                                <?php foreach ($conditions as $condition_index => $condition) : ?>
                                    <div class="canna-rule-condition">
                                        <?php self::render_condition_fields($group_index, $condition_index, $condition); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button button-secondary add-condition-btn"><?php _e('Add Condition (AND)', 'canna-rewards'); ?></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button type="button" id="add-rule-group-btn" class="button button-primary"><?php _e('Add Condition Group (OR)', 'canna-rewards'); ?></button>

        <?php
        // JavaScript templates for cloning
        self::render_js_templates();
    }

    /**
     * Helper to render a single row of condition fields.
     */
    private static function render_condition_fields($group_index, $condition_index, $current_values = []) {
        $subject_options = self::get_subject_options();
        $operator_options = self::get_operator_options();
        $current_subject = $current_values['subject'] ?? '';
        $current_operator = $current_values['operator'] ?? '==';
        $current_value = $current_values['value'] ?? '';
        ?>
        <select name="rule_groups[<?php echo $group_index; ?>][<?php echo $condition_index; ?>][subject]">
            <?php foreach ($subject_options as $group_label => $options) : ?>
                <optgroup label="<?php echo esc_attr($group_label); ?>">
                    <?php foreach ($options as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($current_subject, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>

        <select name="rule_groups[<?php echo $group_index; ?>][<?php echo $condition_index; ?>][operator]">
            <?php foreach ($operator_options as $val => $label) : ?>
                <option value="<?php echo esc_attr($val); ?>" <?php selected($current_operator, $val); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        
        <input type="text" name="rule_groups[<?php echo $group_index; ?>][<?php echo $condition_index; ?>][value]" value="<?php echo esc_attr($current_value); ?>" placeholder="Value" />
        
        <button type="button" class="button button-secondary remove-condition-btn">Remove</button>
        <?php
    }

    /**
     * Renders the JS templates and inline script for the dynamic rule builder.
     */
    private static function render_js_templates() {
        ?>
        <script type="text/template" id="canna-rule-group-template">
            <div class="canna-rule-group">
                <div class="canna-rule-group-header">
                    <h4><?php printf(__('Condition Group #%s (All must be true)', 'canna-rewards'), '__GROUP_INDEX_LABEL__'); ?></h4>
                    <button type="button" class="button button-secondary remove-group-btn"><?php _e('Remove Group', 'canna-rewards'); ?></button>
                </div>
                <div class="conditions-container">
                    <!-- Conditions will be added here -->
                </div>
                <button type="button" class="button button-secondary add-condition-btn"><?php _e('Add Condition (AND)', 'canna-rewards'); ?></button>
            </div>
        </script>

        <script type="text/template" id="canna-rule-condition-template">
            <div class="canna-rule-condition">
                <?php self::render_condition_fields('__GROUP_INDEX__', '__CONDITION_INDEX__'); ?>
            </div>
        </script>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var container = $('#rule-builder-container');

                // Add Group
                $('#add-rule-group-btn').on('click', function() {
                    var groupIndex = container.find('.canna-rule-group').length;
                    var groupTemplate = $('#canna-rule-group-template').html();
                    groupTemplate = groupTemplate.replace(/__GROUP_INDEX_LABEL__/g, groupIndex + 1);
                    var newGroup = $(groupTemplate);
                    container.append(newGroup);
                    // Add the first condition automatically
                    addCondition(newGroup, groupIndex);
                    updateNames(newGroup, groupIndex);
                });

                // Add Condition
                container.on('click', '.add-condition-btn', function() {
                    var group = $(this).closest('.canna-rule-group');
                    var groupIndex = container.find('.canna-rule-group').index(group);
                    addCondition(group, groupIndex);
                });
                
                // Remove Condition
                container.on('click', '.remove-condition-btn', function() {
                    var group = $(this).closest('.canna-rule-group');
                    $(this).closest('.canna-rule-condition').remove();
                    var groupIndex = container.find('.canna-rule-group').index(group);
                    updateNames(group, groupIndex);
                });

                // Remove Group
                container.on('click', '.remove-group-btn', function() {
                    $(this).closest('.canna-rule-group').remove();
                    updateGroupLabelsAndNames();
                });
                
                function addCondition(group, groupIndex) {
                    var conditionsContainer = group.find('.conditions-container');
                    var conditionIndex = conditionsContainer.find('.canna-rule-condition').length;
                    var conditionTemplate = $('#canna-rule-condition-template').html();
                    var newConditionHtml = conditionTemplate.replace(/__GROUP_INDEX__/g, groupIndex).replace(/__CONDITION_INDEX__/g, conditionIndex);
                    conditionsContainer.append(newConditionHtml);
                }

                function updateGroupLabelsAndNames() {
                    container.find('.canna-rule-group').each(function(groupIndex) {
                        $(this).find('h4').text('Condition Group #' + (groupIndex + 1) + ' (All must be true)');
                        updateNames($(this), groupIndex);
                    });
                }

                function updateNames(group, groupIndex) {
                     $(group).find('.canna-rule-condition').each(function(conditionIndex) {
                        $(this).find('select, input').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                var newName = name.replace(/rule_groups\[\d+\]\[\d+\]/, 'rule_groups[' + groupIndex + '][' + conditionIndex + ']');
                                $(this).attr('name', newName);
                            }
                        });
                    });
                }
            });
        </script>
        <?php
    }

    /**
     * Saves the custom metabox data.
     */
    public function save_achievement_metabox_data($post_id) {
        if (!isset($_POST['canna_achievement_metabox_nonce']) || !wp_verify_nonce($_POST['canna_achievement_metabox_nonce'], 'canna_save_achievement_metabox_data')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Standard fields
        $fields_to_save = ['achievement_key', 'trigger_type', 'type', 'rarity', 'icon_url'];
        foreach ($fields_to_save as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        if (isset($_POST['points_reward'])) {
            update_post_meta($post_id, 'points_reward', absint($_POST['points_reward']));
        }
        update_post_meta($post_id, 'is_active', isset($_POST['is_active']) ? 1 : 0);

        // --- Save Rule Builder Data ---
        if (isset($_POST['rule_groups']) && is_array($_POST['rule_groups'])) {
            $sanitized_groups = [];
            foreach ($_POST['rule_groups'] as $group_index => $conditions) {
                if (is_array($conditions)) {
                    $sanitized_conditions = [];
                    foreach ($conditions as $condition_index => $condition) {
                        if (is_array($condition) && !empty($condition['subject']) && !empty($condition['value'])) {
                            $sanitized_conditions[] = [
                                'subject'  => sanitize_key($condition['subject']),
                                'operator' => sanitize_text_field($condition['operator']),
                                'value'    => sanitize_text_field($condition['value']),
                            ];
                        }
                    }
                    if (!empty($sanitized_conditions)) {
                        $sanitized_groups[] = $sanitized_conditions;
                    }
                }
            }
            update_post_meta($post_id, 'rule_groups', $sanitized_groups);
        } else {
            // If no rule groups are submitted, delete the meta key to represent "no rules".
            delete_post_meta($post_id, 'rule_groups');
        }
    }
}