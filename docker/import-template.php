<?php
/**
 * Importa wp-content/elementor-templates/planos-nescon.json como a página
 * "Planos" do site, substituindo a referência ao menu principal pelo ID
 * real criado em docker/provision.sh (via $_SERVER['NESCON_MENU_ID']).
 *
 * Uso: wp eval-file docker/import-template.php  (chamado por provision.sh)
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Execute via 'wp eval-file'.\n");
    exit(1);
}

$json_path  = '/usr/local/share/nescon/elementor-templates/planos-nescon.json';
$page_title = 'Planos';
$page_slug  = 'planos';
$menu_id    = getenv('NESCON_MENU_ID') ?: '';

if (!file_exists($json_path)) {
    fwrite(STDERR, "Template não encontrado em $json_path\n");
    exit(1);
}

$template = json_decode(file_get_contents($json_path), true);
if (!is_array($template) || !isset($template['content'])) {
    fwrite(STDERR, "JSON do template inválido.\n");
    exit(1);
}

/**
 * Substitui, em toda a árvore de elementos, o "menu" do widget Nav Menu
 * pelo ID do menu WordPress criado no provisionamento.
 */
function nescon_patch_nav_menu(array &$elements, string $menu_id): void
{
    foreach ($elements as &$el) {
        if (($el['widgetType'] ?? '') === 'nav-menu' && $menu_id !== '') {
            $el['settings']['menu'] = $menu_id;
        }
        if (!empty($el['elements'])) {
            nescon_patch_nav_menu($el['elements'], $menu_id);
        }
    }
}

nescon_patch_nav_menu($template['content'], $menu_id);

$existing = get_page_by_path($page_slug, OBJECT, 'page');

$postarr = [
    'post_title'   => $page_title,
    'post_name'    => $page_slug,
    'post_status'  => 'publish',
    'post_type'    => 'page',
    'post_content' => '',
];

if ($existing) {
    $postarr['ID'] = $existing->ID;
    $page_id = wp_update_post($postarr, true);
} else {
    $page_id = wp_insert_post($postarr, true);
}

if (is_wp_error($page_id)) {
    fwrite(STDERR, 'Falha ao criar/atualizar a página: ' . $page_id->get_error_message() . "\n");
    exit(1);
}

update_post_meta($page_id, '_elementor_edit_mode', 'builder');
update_post_meta($page_id, '_elementor_data', wp_slash(wp_json_encode($template['content'])));
update_post_meta(
    $page_id,
    '_elementor_version',
    defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '3.0.0'
);

if (!empty($template['page_settings'])) {
    update_post_meta($page_id, '_elementor_page_settings', $template['page_settings']);
}

// Define a página "Planos" como página inicial do site.
update_option('show_on_front', 'page');
update_option('page_on_front', $page_id);

if (class_exists('\Elementor\Plugin')) {
    try {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    } catch (\Throwable $e) {
        // Não é crítico: o CSS é regenerado na primeira visita à página.
    }
}

echo "[nescon] Template importado na página #$page_id ($page_slug).\n";
