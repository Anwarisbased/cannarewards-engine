<?php
namespace CannaRewards\Admin;

final class FieldFactory {
    public function render_text_input(string $name, string $value, array $args = []): void {
        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="%s" placeholder="%s" />',
            esc_attr($args['type'] ?? 'text'),
            esc_attr($args['id'] ?? $name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($args['class'] ?? 'regular-text'),
            esc_attr($args['placeholder'] ?? '')
        );
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_select(string $name, string $selected, array $options, array $args = []): void {
        printf(
            '<select id="%s" name="%s" class="%s">',
            esc_attr($args['id'] ?? $name),
            esc_attr($name),
            esc_attr($args['class'] ?? 'regular-text')
        );
        
        foreach ($options as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($selected, $option_value, false),
                esc_html($option_label)
            );
        }
        
        echo '</select>';
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_checkbox(string $name, bool $checked, array $args = []): void {
        printf(
            '<input type="checkbox" id="%s" name="%s" value="1" %s />',
            esc_attr($args['id'] ?? $name),
            esc_attr($name),
            checked($checked, true, false)
        );
        
        if (!empty($args['label'])) {
            printf('<label for="%s">%s</label>', esc_attr($args['id'] ?? $name), esc_html($args['label']));
        }
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_textarea(string $name, string $value, array $args = []): void {
        printf(
            '<textarea id="%s" name="%s" class="%s" placeholder="%s" rows="%s">%s</textarea>',
            esc_attr($args['id'] ?? $name),
            esc_attr($name),
            esc_attr($args['class'] ?? 'large-text'),
            esc_attr($args['placeholder'] ?? ''),
            esc_attr($args['rows'] ?? '3'),
            esc_textarea($value)
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
}