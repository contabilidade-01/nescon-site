<?php

if (!defined('ABSPATH')) {
    exit;
}

$nescon_dynamic_content = null;

if (have_posts()) {
    the_post();

    // O Elementor exige que o modelo execute the_content(). O buffer permite
    // decidir depois se o conteúdo editável substitui ou não a página estática.
    ob_start();
    the_content();
    $conteudo_wordpress = (string) ob_get_clean();

    $elementor_preview = isset($_GET['elementor-preview']);
    $elementor_ativo = get_post_meta(get_the_ID(), '_elementor_edit_mode', true) === 'builder'
        || get_post_meta(get_the_ID(), '_elementor_data', true) !== '';
    $possui_conteudo = trim(wp_strip_all_tags($conteudo_wordpress)) !== ''
        || str_contains($conteudo_wordpress, 'elementor');

    if ($elementor_preview || $elementor_ativo || $possui_conteudo) {
        $nescon_dynamic_content = $conteudo_wordpress;
    }

    // Artigo do blog escrito no WordPress: o tema monta a página inteira no
    // padrão dos artigos (inc/artigos.php), inclusive na prévia do rascunho.
    if ($possui_conteudo && !$elementor_ativo && nescon_eh_artigo(get_post())) {
        $nescon_artigo = get_post();
    }
}

require __DIR__ . '/static-render.php';

