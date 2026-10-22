<?php

if (!defined('ABSPATH')) {
    exit;
}

$request_path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$request_path = trim(rawurldecode($request_path), '/');
$request_path = preg_replace('/\.html$/', '', $request_path) ?: '';
$relative_file = $request_path === '' || $request_path === 'inicio'
    ? 'index.html'
    : $request_path . '.html';

$static_root = realpath(get_template_directory() . '/static');
$candidate = $static_root ? realpath($static_root . '/' . $relative_file) : false;

if (!$static_root || !$candidate || !str_starts_with($candidate, $static_root) || !is_file($candidate)) {
    $candidate = $static_root ? $static_root . '/404.html' : false;
    status_header(404);
}

if (!$candidate || !is_file($candidate)) {
    wp_die('Página não encontrada.');
}

$html = file_get_contents($candidate);
$asset_base = trailingslashit(get_template_directory_uri()) . 'static';

$html = str_replace(
    [
        'href="/_astro/',
        'src="/_astro/',
        'href="/favicon.svg"',
        'src="/img/',
        'href="/img/',
    ],
    [
        'href="' . $asset_base . '/_astro/',
        'src="' . $asset_base . '/_astro/',
        'href="' . $asset_base . '/favicon.svg"',
        'src="' . $asset_base . '/img/',
        'href="' . $asset_base . '/img/',
    ],
    $html
);

// As opções de imagens responsivas também precisam apontar para a pasta do tema.
$html = preg_replace_callback(
    '/\bsrcset="([^"]+)"/i',
    static function (array $matches) use ($asset_base): string {
        return 'srcset="' . str_replace('/img/', $asset_base . '/img/', $matches[1]) . '"';
    },
    $html
) ?? $html;

// Quando a página foi editada no Elementor ou no editor do WordPress,
// troca somente o conteúdo central e preserva o cabeçalho e o rodapé do tema.
if (isset($nescon_dynamic_content)) {
    $conteudo = '<main id="conteudo" class="flex-1">' . $nescon_dynamic_content . '</main>';
    $html = preg_replace('/<main\b[^>]*id="conteudo"[^>]*>.*?<\/main>/is', $conteudo, $html, 1) ?? $html;
}

ob_start();
wp_head();
$head = ob_get_clean();

ob_start();
wp_body_open();
$body_open = ob_get_clean();

ob_start();
wp_footer();
$footer = ob_get_clean();

$html = str_replace('</head>', $head . '</head>', $html);
$html = preg_replace('/(<body[^>]*>)/i', '$1' . $body_open, $html, 1);
$html = str_replace('</body>', $footer . '</body>', $html);

echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
