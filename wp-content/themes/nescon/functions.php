<?php
/**
 * Nescon Contabilidade - child theme of OceanWP.
 */

defined('ABSPATH') || exit;

define('NESCON_THEME_VERSION', wp_get_theme()->get('Version'));

/**
 * Enqueue parent + child stylesheets and the Google Fonts used by the
 * "Planos" Elementor template (Raleway / Poppins / League Spartan).
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'nescon-google-fonts',
        'https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,400;0,600;0,700;0,800;1,600&family=Poppins:wght@400;500;600;700&family=League+Spartan:wght@700;800&display=swap',
        [],
        null
    );

    wp_enqueue_style('nescon-parent-style', get_template_directory_uri() . '/style.css');

    wp_enqueue_style(
        'nescon-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['nescon-parent-style'],
        NESCON_THEME_VERSION
    );
}, 20);

/**
 * Register the main navigation menu used by the header's Elementor
 * Nav Menu widget (menu slug: main-menu).
 */
add_action('after_setup_theme', function () {
    register_nav_menus([
        'main-menu' => __('Menu Principal', 'nescon'),
    ]);
});
