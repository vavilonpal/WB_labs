<?php
/*
Plugin Name: USM Notes
Description: Плагин заметок с приоритетами и датой напоминания
Version: 1.0
Author: Sergey
*/

// =======================
// CPT
// =======================
function usm_register_notes_cpt() {
    register_post_type('usm_note', [
        'labels' => [
            'name' => 'Заметки',
            'singular_name' => 'Заметка'
        ],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-edit',
        'supports' => ['title', 'editor', 'author', 'thumbnail']
    ]);
}
add_action('init', 'usm_register_notes_cpt');

// =======================
// Taxonomy
// =======================
function usm_register_priority_taxonomy() {
    register_taxonomy('priority', 'usm_note', [
        'labels' => [
            'name' => 'Приоритет',
            'singular_name' => 'Приоритет'
        ],
        'hierarchical' => true,
        'public' => true
    ]);
}
add_action('init', 'usm_register_priority_taxonomy');

// =======================
// Meta Box
// =======================
function usm_add_meta_box() {
    add_meta_box('usm_due_date', 'Дата напоминания', 'usm_meta_box_html', 'usm_note');
}
add_action('add_meta_boxes', 'usm_add_meta_box');

function usm_meta_box_html($post) {
    $value = get_post_meta($post->ID, '_due_date', true);
    wp_nonce_field('usm_save_date', 'usm_nonce');

    echo '<input type="date" name="usm_due_date" value="' . esc_attr($value) . '" required>';
}

// =======================
// Save Meta
// =======================
function usm_save_meta($post_id) {

    if (!isset($_POST['usm_nonce']) || !wp_verify_nonce($_POST['usm_nonce'], 'usm_save_date')) return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['usm_due_date'])) {
        $date = $_POST['usm_due_date'];

        if ($date < date('Y-m-d')) {
            add_filter('redirect_post_location', function($location) {
                return add_query_arg('usm_error', '1', $location);
            });
            return;
        }

        update_post_meta($post_id, '_due_date', sanitize_text_field($date));
    }
}
add_action('save_post', 'usm_save_meta');

// =======================
// Admin Error
// =======================
function usm_admin_error() {
    if (isset($_GET['usm_error'])) {
        echo '<div class="error"><p>Дата не может быть в прошлом</p></div>';
    }
}
add_action('admin_notices', 'usm_admin_error');

// =======================
// Shortcode
// =======================
function usm_notes_shortcode($atts) {

    $atts = shortcode_atts([
        'priority' => '',
        'before_date' => ''
    ], $atts);

    $meta_query = [];

    if ($atts['before_date']) {
        $meta_query[] = [
            'key' => '_due_date',
            'value' => $atts['before_date'],
            'compare' => '<=',
            'type' => 'DATE'
        ];
    }

    $tax_query = [];

    if ($atts['priority']) {
        $tax_query[] = [
            'taxonomy' => 'priority',
            'field' => 'slug',
            'terms' => $atts['priority']
        ];
    }

    $query = new WP_Query([
        'post_type' => 'usm_note',
        'meta_query' => $meta_query,
        'tax_query' => $tax_query
    ]);

    if (!$query->have_posts()) {
        return "<p>Нет заметок с заданными параметрами</p>";
    }

    $output = "<div class='usm-notes'>";

    while ($query->have_posts()) {
        $query->the_post();
        $date = get_post_meta(get_the_ID(), '_due_date', true);

        $output .= "<div class='note'>";
        $output .= "<h3>" . get_the_title() . "</h3>";
        $output .= "<p>" . get_the_content() . "</p>";
        $output .= "<small>Дата: $date</small>";
        $output .= "</div>";
    }

    wp_reset_postdata();

    return $output . "</div>";
}
add_shortcode('usm_notes', 'usm_notes_shortcode');

// =======================
// Styles
// =======================
function usm_notes_styles() {
    echo "<style>
    .usm-notes { padding:10px; }
    .note { border:1px solid #ccc; margin:10px 0; padding:10px; }
    </style>";
}
add_action('wp_head', 'usm_notes_styles');