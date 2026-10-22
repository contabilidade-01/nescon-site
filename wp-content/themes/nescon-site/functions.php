<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/inc/lead-capture.php';
require_once get_template_directory() . '/inc/elementor-pilot-rollback.php';
require_once get_template_directory() . '/inc/artigos.php';

add_action('after_setup_theme', static function (): void {
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['script', 'style', 'gallery', 'caption']);
});

// O servidor ainda possui uma instalação antiga no diretório físico /blog.
// Regras no topo fazem o domínio principal entregar o blog deste tema, sem
// apagar a instalação antiga usada pelo subdomínio blog.*.
add_action('init', static function (): void {
    add_rewrite_rule('^blog/?$', 'index.php?pagename=blog', 'top');
    add_rewrite_rule('^blog/([^/]+)/?$', 'index.php?pagename=blog/$matches[1]', 'top');
}, 1);

/**
 * Faz o LiteSpeed encaminhar /blog ao WordPress principal antes de verificar
 * a pasta física legada. A condição de host preserva blog.nesconcontabilidade.com.br.
 */
function nescon_site_install_blog_route_override(): void
{
    $route_version = '1.4.2';
    if (get_option('nescon_site_blog_route_version') === $route_version) {
        return;
    }

    if (!function_exists('insert_with_markers')) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }

    $host_condition = 'RewriteCond %{HTTP_HOST} ^(?:www\\.)?nesconcontabilidade\\.com\\.br$ [NC]';
    $root_rules = [
        'RewriteEngine On',
        $host_condition,
        'RewriteRule ^blog/?$ index.php?pagename=blog [L,QSA]',
        $host_condition,
        'RewriteRule ^blog/index\\.php$ index.php?pagename=blog [L,QSA]',
        $host_condition,
        'RewriteRule ^blog/([^/]+)/?$ index.php?pagename=blog/$1 [L,QSA]',
    ];

    // A requisição da capa entra na pasta antiga antes da regra da raiz.
    // Este marcador dentro de /blog devolve somente o host principal ao site novo.
    $legacy_blog_rules = [
        'RewriteEngine On',
        $host_condition,
        'RewriteRule ^(?:index\\.php)?$ /index.php?pagename=blog [L,QSA]',
    ];

    $root_written = insert_with_markers(ABSPATH . '.htaccess', 'Nescon Blog', $root_rules);
    $legacy_htaccess = trailingslashit(ABSPATH) . 'blog/.htaccess';
    $legacy_written = is_dir(trailingslashit(ABSPATH) . 'blog')
        && insert_with_markers($legacy_htaccess, 'Nescon Main Blog', $legacy_blog_rules);

    if ($root_written && $legacy_written) {
        update_option('nescon_site_blog_route_version', $route_version);
    }
}

add_action('after_switch_theme', 'nescon_site_install_blog_route_override');
add_action('admin_init', 'nescon_site_install_blog_route_override');

add_action('init', static function (): void {
    remove_action('wp_head', 'rel_canonical');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
});

// O tema entrega HTML estático e não usa os blocos ou formulários desses plugins.
// Impedir o carregamento público evita CSS bloqueante e JavaScript sem função.
add_action('wp_enqueue_scripts', static function (): void {
    $styles = [
        'wp-block-library',
        'wp-block-library-theme',
        'classic-theme-styles',
        'global-styles',
        'hostinger-reach-subscription-block',
        'contact-form-7',
    ];

    foreach ($styles as $handle) {
        wp_dequeue_style($handle);
    }

    $scripts = [
        'hostinger-reach-embed',
        'contact-form-7',
        'swv',
    ];

    foreach ($scripts as $handle) {
        wp_dequeue_script($handle);
    }
}, 1000);

function nescon_site_create_page(string $title, string $slug, int $parent = 0): int
{
    $path = $parent ? get_post_field('post_name', $parent) . '/' . $slug : $slug;
    $existing = get_page_by_path($path, OBJECT, 'page');

    if ($existing instanceof WP_Post) {
        return (int) $existing->ID;
    }

    $page_id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_parent' => $parent,
        'post_content' => '',
    ]);

    return is_wp_error($page_id) ? 0 : (int) $page_id;
}

function nescon_site_sync_pages(): void
{
    $content_version = '1.4.2';
    if (get_option('nescon_site_content_version') === $content_version) {
        return;
    }

    $home_id = nescon_site_create_page('Início', 'inicio');

    $pages = [
        'abrir-empresa' => 'Abrir empresa',
        'contabilidade-simples-nacional' => 'Contabilidade para Simples Nacional',
        'contabilidade-em-guarulhos' => 'Contabilidade em Guarulhos',
        'contato' => 'Contato',
        'politica-de-privacidade' => 'Política de privacidade',
        'regularizacao-mei' => 'Regularização de MEI',
        'sobre' => 'Sobre',
        'trocar-de-contador' => 'Trocar de contador',
        'contabilidade-para-oticas' => 'Contabilidade para Óticas',
        'contabilidade-para-psicologos' => 'Contabilidade para Psicólogos',
        'contabilidade-para-nutricionistas' => 'Contabilidade para Nutricionistas',
        'contabilidade-para-prestadores-de-servicos' => 'Contabilidade para Prestadores de Serviços',
        'contabilidade-para-salao-de-beleza' => 'Contabilidade para Salão de Beleza',
        'contabilidade-para-restaurantes' => 'Contabilidade para Restaurantes',
        'contabilidade-para-comercio' => 'Contabilidade para Comércio',
        'contabilidade-para-dentistas' => 'Contabilidade para Dentistas',
        'contabilidade-para-ecommerce' => 'Contabilidade para E-commerce',
        'contabilidade-para-construcao-civil' => 'Contabilidade para Construção Civil',
    ];

    foreach ($pages as $slug => $title) {
        nescon_site_create_page($title, $slug);
    }

    $blog_id = nescon_site_create_page('Conteúdos', 'blog');
    $articles = [
        'como-trocar-de-contador' => 'Como trocar de contador',
        'desenquadramento-do-mei' => 'Desenquadramento do MEI',
        'o-que-precisa-para-abrir-empresa' => 'O que é preciso para abrir uma empresa',
        'quanto-custa-um-contador' => 'Quanto custa um contador',
    ];

    foreach ($articles as $slug => $title) {
        nescon_site_create_page($title, $slug, $blog_id);
    }

    if ($home_id) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $home_id);
    }

    update_option('nescon_site_content_version', $content_version);
    flush_rewrite_rules();
}

add_action('after_switch_theme', 'nescon_site_sync_pages');
add_action('init', 'nescon_site_sync_pages');

add_action('template_redirect', static function (): void {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $redirects = [
        'abrir-mei' => 'regularizacao-mei',
        'mei-para-me' => 'regularizacao-mei',
        'blog/mei-ou-me-qual-escolher' => 'regularizacao-mei',
    ];

    if (isset($redirects[$path])) {
        wp_safe_redirect(home_url('/' . $redirects[$path] . '/'), 301);
        exit;
    }
});

add_filter('robots_txt', static function (string $output, bool $public): string {
    if (!$public) {
        return $output;
    }

    return "User-agent: *\nAllow: /\nDisallow: /wp-admin/\nDisallow: /painel\n\nSitemap: " . home_url('/sitemap-index.xml') . "\n";
}, 20, 2);

add_action('template_redirect', static function (): void {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $files = [
        'sitemap-index.xml' => ['sitemap-index.xml', 'application/xml; charset=utf-8'],
        'sitemap-0.xml' => ['sitemap-0.xml', 'application/xml; charset=utf-8'],
        'llms.txt' => ['llms.txt', 'text/plain; charset=utf-8'],
    ];

    if (!isset($files[$path])) {
        return;
    }

    [$relative, $content_type] = $files[$path];
    $file = get_template_directory() . '/static/' . $relative;
    if (!is_file($file)) {
        return;
    }

    $conteudo = (string) file_get_contents($file);
    if ($relative === 'sitemap-0.xml') {
        $conteudo = nescon_sitemap_com_artigos($conteudo);
    } elseif ($relative === 'llms.txt') {
        $conteudo = nescon_llms_com_artigos($conteudo);
    }

    status_header(200);
    header('Content-Type: ' . $content_type);
    header('X-Robots-Tag: noindex', true);
    echo $conteudo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}, 1);
