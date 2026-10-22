<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Remove a página piloto do Elementor e restaura o conteúdo estático original. */
add_action('init', static function (): void {
    $rollback_version = '1.0.0';
    if (get_option('nescon_elementor_pilot_rollback') === $rollback_version) {
        return;
    }

    $pagina = get_page_by_path('abrir-empresa', OBJECT, 'page');
    if (!$pagina instanceof WP_Post) {
        return;
    }

    foreach ([
        '_elementor_edit_mode',
        '_elementor_template_type',
        '_elementor_version',
        '_elementor_data',
        '_elementor_page_settings',
        '_elementor_css',
        '_elementor_controls_usage',
    ] as $meta) {
        delete_post_meta($pagina->ID, $meta);
    }

    wp_update_post(['ID' => $pagina->ID, 'post_content' => '']);
    delete_option('nescon_elementor_pilot_version');
    update_option('nescon_elementor_pilot_rollback', $rollback_version);

    if (class_exists('\\Elementor\\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
}, 30);
