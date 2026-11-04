<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Painel "Conteúdos criados" no wp-admin (menu Pedir artigo): tudo o que o
 * gerador de artigos já produziu, com números por situação, filtros, busca e
 * uma tela de leitura de cada artigo (texto completo, imagem, resumo,
 * descrição para o Google, pontos de revisão e histórico do pedido), sem
 * precisar abrir o editor.
 */

const NESCON_CONTEUDOS_POR_PAGINA = 20;

add_action('admin_menu', static function (): void {
    add_submenu_page('nescon-pedir-artigo', 'Conteúdos criados', 'Conteúdos criados', 'edit_pages', 'nescon-conteudos', 'nescon_conteudos_tela');
}, 11);

/** Situações do painel, na ordem das abas. */
function nescon_conteudos_situacoes(): array
{
    return [
        'producao' => 'Em produção',
        'rascunho' => 'Rascunho pronto',
        'revisao' => 'Aguardando revisão',
        'agendado' => 'Agendado',
        'publicado' => 'Publicado',
        'erro' => 'Com erro',
    ];
}

/** Situação de um artigo do gerador, juntando o andamento do pedido e o status do WordPress. */
function nescon_conteudo_situacao(WP_Post $p): string
{
    $status = (string) get_post_meta($p->ID, 'nescon_pedido_status', true);
    if (in_array($status, ['fila', 'escrevendo', 'ilustrando'], true)) {
        return 'producao';
    }
    if ('erro' === $status) {
        return 'erro';
    }

    return match ($p->post_status) {
        'publish' => 'publicado',
        'pending' => 'revisao',
        'future' => 'agendado',
        default => 'rascunho',
    };
}

function nescon_conteudo_palavras(WP_Post $p): int
{
    return str_word_count(wp_strip_all_tags($p->post_content), 0, 'ÁÉÍÓÚÂÊÔÃÕÇÀáéíóúâêôãõçà');
}

function nescon_conteudo_selo(string $situacao): string
{
    return sprintf('<span class="nescon-selo nescon-selo-%s">%s</span>', esc_attr($situacao), esc_html(nescon_conteudos_situacoes()[$situacao] ?? $situacao));
}

/** Miniatura do artigo: a imagem destacada ou a foto do serviço, como no site. */
function nescon_conteudo_miniatura(WP_Post $p, string $tamanho = 'medium'): string
{
    $id = get_post_thumbnail_id($p);
    if ($id) {
        return (string) wp_get_attachment_image($id, $tamanho, false, ['class' => 'nescon-mini', 'loading' => 'lazy']);
    }
    $imagem = nescon_artigo_imagem($p);
    // A foto do serviço tem caminho relativo (/img/...), servido pela pasta do tema.
    $src = str_starts_with($imagem['src'], '/img/') ? trailingslashit(get_template_directory_uri()) . 'static' . $imagem['src'] : $imagem['src'];

    return sprintf('<img class="nescon-mini" src="%s" alt="%s" loading="lazy">', esc_url($src), esc_attr($imagem['alt']));
}

function nescon_conteudo_data(string $data): string
{
    return esc_html(wp_date('d/m/Y H:i', strtotime(get_gmt_from_date($data)) ?: time()));
}

function nescon_conteudos_estilo(): void
{
    echo '<style>
.nescon-numeros{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin:16px 0 20px;max-width:1100px}
.nescon-numero{display:block;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 14px;text-decoration:none;color:#1d2327}
.nescon-numero:hover,.nescon-numero.ativo{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
.nescon-numero strong{display:block;font-size:26px;line-height:1.2;font-variant-numeric:tabular-nums}
.nescon-numero span{font-size:13px;color:#50575e}
.nescon-filtros{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 12px}
.nescon-conteudos td{vertical-align:middle}
.nescon-conteudos .col-img{width:96px}
.nescon-mini{width:88px;height:56px;object-fit:cover;border-radius:4px;display:block;background:#f0f0f1}
.nescon-selo{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;white-space:nowrap}
.nescon-selo-producao{background:#e5f0fa;color:#135e96}
.nescon-selo-rascunho{background:#f0f0f1;color:#3c434a}
.nescon-selo-revisao{background:#fcf3d9;color:#8a6100}
.nescon-selo-agendado{background:#efe7fb;color:#5b2a9e}
.nescon-selo-publicado{background:#e6f4ea;color:#1a6b34}
.nescon-selo-erro{background:#fbe9e9;color:#b32d2e}
.nescon-leitura{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:24px;max-width:1200px;margin-top:16px}
@media (max-width:960px){.nescon-leitura{grid-template-columns:1fr}}
.nescon-artigo{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:24px 32px;font-size:15px;line-height:1.65}
.nescon-artigo h1{font-size:26px;line-height:1.25;margin:0 0 12px}
.nescon-artigo h2{font-size:20px;margin:1.6em 0 .5em}
.nescon-artigo h3{font-size:17px}
.nescon-artigo img.capa{width:100%;height:auto;border-radius:6px;margin:8px 0 16px}
.nescon-artigo table{border-collapse:collapse;width:100%}
.nescon-artigo th,.nescon-artigo td{border:1px solid #dcdcde;padding:6px 10px;text-align:left}
.nescon-artigo details{border:1px solid #dcdcde;border-radius:4px;padding:8px 12px;margin:8px 0}
.nescon-artigo summary{font-weight:600;cursor:pointer}
.nescon-resumo{background:#f6f7f7;border-left:4px solid #2271b1;padding:10px 14px;margin:0 0 16px}
.nescon-lado .postbox{margin-bottom:16px}
.nescon-lado .inside dl{margin:0}
.nescon-lado dt{font-weight:600;margin-top:8px}
.nescon-lado dd{margin:2px 0 0}
.nescon-controles{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:6px}
.nescon-controles form{display:inline-flex;gap:4px;align-items:center;margin:0}
.nescon-agendar summary{cursor:pointer;color:#2271b1;font-size:12px}
.nescon-agendar[open]{flex-basis:100%}
.nescon-agendar form{margin-top:4px!important}
.nescon-controles input[type=datetime-local]{min-height:24px;font-size:12px}
.nescon-registro{margin:.5em 0 0 1.5em;font-size:12px;color:#50575e}
.nescon-registro time{font-variant-numeric:tabular-nums;color:#8c8f94}
</style>';
}

function nescon_conteudos_tela(): void
{
    $aviso = nescon_conteudos_acoes();
    if ($aviso !== '') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($aviso) . '</p></div>';
    }
    $id = (int) ($_GET['artigo'] ?? 0);
    if ($id && 'trash' !== get_post_status($id)) {
        nescon_conteudo_leitura($id);

        return;
    }

    $pedidos = get_posts([
        'post_type' => 'page',
        'post_status' => ['draft', 'pending', 'publish', 'future', 'private'],
        'meta_key' => 'nescon_pedido_status',
        'numberposts' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $situacoes = nescon_conteudos_situacoes();
    $contagem = array_fill_keys(array_keys($situacoes), 0);
    $porSituacao = [];
    foreach ($pedidos as $p) {
        $s = nescon_conteudo_situacao($p);
        ++$contagem[$s];
        $porSituacao[$p->ID] = $s;
    }

    $filtro = sanitize_key($_GET['situacao'] ?? '');
    $filtro = isset($situacoes[$filtro]) ? $filtro : '';
    $categoria = sanitize_text_field(wp_unslash($_GET['categoria'] ?? ''));
    $busca = sanitize_text_field(wp_unslash($_GET['busca'] ?? ''));

    $lista = array_values(array_filter($pedidos, static function (WP_Post $p) use ($filtro, $categoria, $busca, $porSituacao): bool {
        if ($filtro !== '' && $porSituacao[$p->ID] !== $filtro) {
            return false;
        }
        if ($categoria !== '' && get_post_meta($p->ID, 'nescon_categoria', true) !== $categoria) {
            return false;
        }
        if ($busca !== '') {
            $onde = $p->post_title . ' ' . get_post_meta($p->ID, 'nescon_pedido_tema', true) . ' ' . $p->post_excerpt;

            return false !== mb_stripos($onde, $busca);
        }

        return true;
    }));

    $paginas = max(1, (int) ceil(count($lista) / NESCON_CONTEUDOS_POR_PAGINA));
    $pagina = min($paginas, max(1, (int) ($_GET['paged'] ?? 1)));
    $lista = array_slice($lista, ($pagina - 1) * NESCON_CONTEUDOS_POR_PAGINA, NESCON_CONTEUDOS_POR_PAGINA);

    $base = admin_url('admin.php?page=nescon-conteudos');
    $palavras = array_sum(array_map('nescon_conteudo_palavras', $pedidos));

    echo '<div class="wrap"><h1 class="wp-heading-inline">Conteúdos criados</h1> ';
    printf('<a class="page-title-action" href="%s">Pedir artigo</a><hr class="wp-header-end">', esc_url(admin_url('admin.php?page=nescon-pedir-artigo')));
    nescon_conteudos_estilo();
    printf(
        '<p>%s criados pelo gerador, somando %s palavras. Clique num número para filtrar.</p>',
        esc_html(sprintf(_n('%s artigo', '%s artigos', count($pedidos)), number_format_i18n(count($pedidos)))),
        esc_html(number_format_i18n($palavras)),
    );

    echo '<div class="nescon-numeros">';
    printf('<a class="nescon-numero%s" href="%s"><strong>%s</strong><span>Todos</span></a>', $filtro === '' ? ' ativo' : '', esc_url($base), esc_html(number_format_i18n(count($pedidos))));
    foreach ($situacoes as $chave => $nome) {
        printf(
            '<a class="nescon-numero%s" href="%s"><strong>%s</strong><span>%s</span></a>',
            $filtro === $chave ? ' ativo' : '',
            esc_url(add_query_arg('situacao', $chave, $base)),
            esc_html(number_format_i18n($contagem[$chave])),
            esc_html($nome),
        );
    }
    echo '</div>';

    echo '<form method="get" class="nescon-filtros"><input type="hidden" name="page" value="nescon-conteudos">';
    if ($filtro !== '') {
        printf('<input type="hidden" name="situacao" value="%s">', esc_attr($filtro));
    }
    echo '<select name="categoria"><option value="">Todas as categorias</option>';
    foreach (nescon_artigo_categorias() as $c) {
        printf('<option%s>%s</option>', selected($categoria, $c, false), esc_html($c));
    }
    echo '</select>';
    printf('<input type="search" name="busca" value="%s" placeholder="Buscar por título ou tema">', esc_attr($busca));
    submit_button('Filtrar', 'secondary', '', false);
    if ($categoria !== '' || $busca !== '') {
        printf(' <a href="%s">Limpar</a>', esc_url($filtro !== '' ? add_query_arg('situacao', $filtro, $base) : $base));
    }
    echo '</form>';

    if (!$lista) {
        echo '<p>' . ($pedidos ? 'Nenhum artigo com esses filtros.' : 'Nenhum artigo criado ainda. Peça o primeiro em <a href="' . esc_url(admin_url('admin.php?page=nescon-pedir-artigo')) . '">Pedir artigo</a>.') . '</p></div>';

        return;
    }

    echo '<table class="widefat striped nescon-conteudos"><thead><tr><th class="col-img"></th><th>Artigo</th><th>Situação</th><th>Categoria</th><th>Palavras</th><th>Pedido em</th><th>Ações</th></tr></thead><tbody>';
    foreach ($lista as $p) {
        $situacao = $porSituacao[$p->ID];
        $ler = add_query_arg('artigo', $p->ID, $base);
        $autor = get_the_author_meta('display_name', (int) $p->post_author);
        echo '<tr>';
        printf('<td class="col-img"><a href="%s">%s</a></td>', esc_url($ler), nescon_conteudo_miniatura($p, 'thumbnail'));
        printf(
            '<td><strong><a href="%s">%s</a></strong><br><small>Pedido: %s%s</small></td>',
            esc_url($ler),
            esc_html(get_the_title($p)),
            esc_html((string) get_post_meta($p->ID, 'nescon_pedido_tema', true)),
            $autor ? esc_html(' · por ' . $autor) : '',
        );
        echo '<td>' . nescon_conteudo_selo($situacao);
        if ('publicado' === $situacao || 'agendado' === $situacao) {
            echo '<br><small>' . ('agendado' === $situacao ? 'para ' : 'em ') . nescon_conteudo_data($p->post_date) . '</small>';
        } elseif ($quando = (int) get_post_meta($p->ID, 'nescon_publicar_em', true)) {
            echo '<br><small>publica em ' . esc_html(wp_date('d/m/Y H:i', $quando)) . ' quando ficar pronto</small>';
        } elseif ('erro' === $situacao) {
            echo '<br><small style="color:#b32d2e">' . esc_html(wp_trim_words((string) get_post_meta($p->ID, 'nescon_pedido_erro', true), 12)) . '</small>';
        }
        echo '</td>';
        printf('<td>%s</td>', esc_html((string) get_post_meta($p->ID, 'nescon_categoria', true) ?: '—'));
        $n = nescon_conteudo_palavras($p);
        printf('<td>%s</td>', $n ? esc_html(number_format_i18n($n)) : '—');
        printf('<td>%s</td>', nescon_conteudo_data($p->post_date));
        echo '<td>';
        printf('<a class="button button-small" href="%s">Ler</a> ', esc_url($ler));
        if (trim($p->post_content) !== '') {
            printf(
                '<a class="button button-small" href="%s" target="_blank">%s</a> ',
                esc_url('publicado' === $situacao ? get_permalink($p) : get_preview_post_link($p)),
                'publicado' === $situacao ? 'Ver no site' : 'Ver como fica',
            );
        }
        printf('<a class="button button-small" href="%s">Editar</a>', esc_url(get_edit_post_link($p->ID, 'url')));
        echo nescon_conteudo_controles($p, $situacao);
        echo '</td></tr>';
    }
    echo '</tbody></table>';

    if ($paginas > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'current' => $pagina,
            'total' => $paginas,
            'prev_text' => '‹',
            'next_text' => '›',
        ]) . '</div></div>';
    }
    echo '</div>';
}

/** Leitura completa de um artigo, com tudo o que o gerador produziu. */
function nescon_conteudo_leitura(int $id): void
{
    $p = get_post($id);
    $voltar = admin_url('admin.php?page=nescon-conteudos');
    if (!$p instanceof WP_Post || !get_post_meta($id, 'nescon_pedido_status', true) || !current_user_can('edit_post', $id)) {
        printf('<div class="wrap"><h1>Conteúdos criados</h1><p>Artigo não encontrado. <a href="%s">Voltar à lista</a></p></div>', esc_url($voltar));

        return;
    }

    $situacao = nescon_conteudo_situacao($p);
    $servico = nescon_artigo_servicos()[get_post_meta($id, 'nescon_servico', true)] ?? null;
    $descricao = (string) get_post_meta($id, 'nescon_descricao', true);
    $pontos = array_filter((array) get_post_meta($id, 'nescon_pedido_pontos', true));
    $orientacoes = (string) get_post_meta($id, 'nescon_pedido_orientacoes', true);
    $ajuste = (string) get_post_meta($id, 'nescon_pedido_ultimo_ajuste', true);
    $imagem = nescon_artigo_imagem($p);
    $tem_texto = trim($p->post_content) !== '';

    nescon_conteudos_estilo();
    echo '<div class="wrap">';
    printf('<p><a href="%s">← Conteúdos criados</a></p>', esc_url($voltar));
    echo '<h1 class="wp-heading-inline">' . esc_html(get_the_title($p)) . '</h1> ' . nescon_conteudo_selo($situacao);
    echo '<p>';
    if ($tem_texto) {
        printf(
            '<a class="button button-primary" href="%s" target="_blank">%s</a> ',
            esc_url('publicado' === $situacao ? get_permalink($p) : get_preview_post_link($p)),
            'publicado' === $situacao ? 'Ver no site' : 'Ver como fica no site',
        );
    }
    printf('<a class="button" href="%s">Editar</a> ', esc_url(get_edit_post_link($id, 'url')));
    if ('publicado' !== $situacao) {
        printf('<a class="button" href="%s">Pedir ajuste ou outra imagem</a>', esc_url(admin_url('admin.php?page=nescon-pedir-artigo')));
    }
    echo '</p>' . nescon_conteudo_controles($p, $situacao, true);

    echo '<div class="nescon-leitura"><div class="nescon-artigo">';
    if (!$tem_texto) {
        echo '<p>O texto ainda não foi escrito. ' . esc_html(nescon_pedido_status_texto($p)) . '</p>';
    } else {
        echo '<h1>' . esc_html(get_the_title($p)) . '</h1>';
        $src = str_starts_with($imagem['src'], '/img/') ? trailingslashit(get_template_directory_uri()) . 'static' . $imagem['src'] : $imagem['src'];
        printf('<img class="capa" src="%s" alt="%s">', esc_url($src), esc_attr($imagem['alt']));
        if ($p->post_excerpt !== '') {
            echo '<div class="nescon-resumo"><strong>Resposta rápida.</strong> ' . esc_html($p->post_excerpt) . '</div>';
        }
        echo wp_kses_post(do_blocks($p->post_content));
        if ($servico) {
            $chamada = (string) get_post_meta($id, 'nescon_chamada_titulo', true) ?: $servico['chamada'];
            $chamada_texto = (string) get_post_meta($id, 'nescon_chamada_texto', true) ?: $servico['texto'];
            echo '<div class="nescon-resumo" style="margin-top:24px"><strong>Chamada no fim do artigo: ' . esc_html($chamada) . '</strong><br>' . esc_html($chamada_texto) . '</div>';
        }
    }
    echo '</div><div class="nescon-lado">';

    echo '<div class="postbox"><h2 class="hndle" style="padding:8px 12px;margin:0">Dados do artigo</h2><div class="inside"><dl>';
    $dados = [
        'Publicação agendada' => 'agendado' === $situacao ? wp_date('d/m/Y H:i', strtotime(get_gmt_from_date($p->post_date))) : (((int) get_post_meta($id, 'nescon_publicar_em', true)) && 'publicado' !== $situacao ? wp_date('d/m/Y H:i', (int) get_post_meta($id, 'nescon_publicar_em', true)) . ' (quando ficar pronto)' : ''),
        'Situação' => nescon_conteudo_situacao($p) === 'erro' ? 'Com erro: ' . get_post_meta($id, 'nescon_pedido_erro', true) : nescon_pedido_status_texto($p),
        'Endereço' => $p->post_name !== '' ? '/blog/' . $p->post_name : '',
        'Categoria' => (string) get_post_meta($id, 'nescon_categoria', true),
        'Serviço relacionado' => $servico['titulo'] ?? '',
        'Palavras' => $tem_texto ? number_format_i18n(nescon_conteudo_palavras($p)) : '',
        'Pedido por' => (string) get_the_author_meta('display_name', (int) $p->post_author),
        'Pedido em' => wp_date('d/m/Y H:i', strtotime(get_gmt_from_date($p->post_date))),
        'Última alteração' => wp_date('d/m/Y H:i', strtotime(get_gmt_from_date($p->post_modified))),
    ];
    foreach (array_filter($dados) as $rotulo => $valor) {
        printf('<dt>%s</dt><dd>%s</dd>', esc_html($rotulo), esc_html($valor));
    }
    echo '</dl></div></div>';

    echo '<div class="postbox"><h2 class="hndle" style="padding:8px 12px;margin:0">O pedido</h2><div class="inside"><dl>';
    printf('<dt>Tema</dt><dd>%s</dd>', esc_html((string) get_post_meta($id, 'nescon_pedido_tema', true)));
    if ($orientacoes !== '') {
        printf('<dt>Orientações</dt><dd>%s</dd>', nl2br(esc_html($orientacoes)));
    }
    if ($ajuste !== '') {
        printf('<dt>Último ajuste pedido</dt><dd>%s</dd>', nl2br(esc_html($ajuste)));
    }
    echo '</dl></div></div>';

    if ($tem_texto) {
        echo '<div class="postbox"><h2 class="hndle" style="padding:8px 12px;margin:0">No Google</h2><div class="inside">';
        printf('<p style="margin:0;color:#1a0dab;font-size:16px">%s</p>', esc_html(get_the_title($p)));
        printf('<p style="margin:2px 0;color:#006621;font-size:12px">%s</p>', esc_html(nescon_artigo_url($p)));
        printf('<p style="margin:0">%s</p>', esc_html(nescon_artigo_descricao($p)));
        if ($descricao !== '') {
            printf('<p class="description">%d caracteres na descrição.</p>', mb_strlen($descricao));
        }
        echo '</div></div>';

        echo '<div class="postbox"><h2 class="hndle" style="padding:8px 12px;margin:0">Imagem</h2><div class="inside">';
        echo nescon_conteudo_miniatura($p, 'medium');
        printf('<p class="description">%s<br>Texto alternativo: %s</p>', has_post_thumbnail($p) ? 'Ilustração criada para o artigo.' : 'Foto do serviço (o artigo não tem ilustração própria).', esc_html($imagem['alt']));
        echo '</div></div>';
    }

    if ($pontos) {
        echo '<div class="postbox"><h2 class="hndle" style="padding:8px 12px;margin:0">Conferir antes de publicar</h2><div class="inside"><ul style="list-style:disc;margin-left:1.2em">';
        foreach ($pontos as $ponto) {
            echo '<li>' . esc_html($ponto) . '</li>';
        }
        echo '</ul></div></div>';
    }

    $registro = nescon_pedido_registro_html($id);
    if ($registro !== '') {
        echo '<div class="postbox"><h2 class="hndle" style="padding:8px 12px;margin:0">Histórico do pedido</h2><div class="inside">' . $registro . '</div></div>';
    }

    echo '</div></div></div>';
}


/*
 * Agendamento da publicação. No pedido (ou depois, num rascunho pronto) quem
 * pode publicar escolhe data e hora; quando o texto fica pronto, o artigo vira
 * "Agendado" no WordPress e é publicado sozinho nessa hora.
 */

/** Data e hora do campo datetime-local (horário do site) em timestamp, ou 0. */
function nescon_conteudo_ler_data(string $valor): int
{
    $data = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $valor, wp_timezone());

    return $data ? $data->getTimestamp() : 0;
}

function nescon_conteudo_agendar(int $id, int $quando): void
{
    $quando = max($quando, time());
    wp_update_post([
        'ID' => $id,
        'post_status' => $quando > time() + 30 ? 'future' : 'publish',
        'post_date' => wp_date('Y-m-d H:i:s', $quando),
        'post_date_gmt' => gmdate('Y-m-d H:i:s', $quando),
        'edit_date' => true,
    ]);
    delete_post_meta($id, 'nescon_publicar_em');
    nescon_pedido_registrar($id, 'publish' === get_post_status($id) ? 'Publicado (a data agendada já tinha passado)' : 'Publicação agendada para ' . wp_date('d/m/Y H:i', $quando));
}

/* Pedido com data marcada: agenda assim que o texto fica pronto. */
$nescon_ao_ficar_pronto = static function ($meta_id, $id, $chave, $valor): void {
    if ('nescon_pedido_status' !== $chave || 'pronto' !== $valor) {
        return;
    }
    $quando = (int) get_post_meta($id, 'nescon_publicar_em', true);
    if ($quando && in_array(get_post_status($id), ['draft', 'pending'], true)) {
        nescon_conteudo_agendar((int) $id, $quando);
    }
};
add_action('added_post_meta', $nescon_ao_ficar_pronto, 10, 4);
add_action('updated_post_meta', $nescon_ao_ficar_pronto, 10, 4);

/**
 * Publica os agendados que já passaram da hora. O WordPress faz isso pelo
 * WP-Cron, que depende de visitas; o cron do servidor (tarefas.php) garante.
 */
function nescon_publicar_agendados(): void
{
    $vencidos = get_posts([
        'post_type' => 'page',
        'post_status' => 'future',
        'numberposts' => 20,
        'fields' => 'ids',
        'date_query' => [['column' => 'post_date_gmt', 'before' => gmdate('Y-m-d H:i:s'), 'inclusive' => true]],
    ]);
    foreach ($vencidos as $id) {
        wp_publish_post($id);
        if (get_post_meta($id, 'nescon_pedido_status', true)) {
            nescon_pedido_registrar((int) $id, 'Publicado no horário agendado');
        }
    }
}

/** Excluir, agendar e cancelar agendamento, nas telas "Pedir artigo" e "Conteúdos criados". */
function nescon_conteudos_acoes(): string
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id || !get_post_meta($id, 'nescon_pedido_status', true)) {
        return '';
    }

    if (isset($_POST['nescon_excluir']) && check_admin_referer('nescon_excluir_' . $id) && current_user_can('delete_post', $id)) {
        $titulo = get_the_title($id);
        wp_trash_post($id);

        return 'Pedido "' . $titulo . '" excluído. Ele fica na lixeira de Páginas por 30 dias, se precisar recuperar.';
    }

    if (isset($_POST['nescon_agendar']) && check_admin_referer('nescon_agendar_' . $id) && current_user_can('publish_post', $id)) {
        $quando = nescon_conteudo_ler_data(sanitize_text_field(wp_unslash($_POST['publicar_em'] ?? '')));
        if (!$quando) {
            return '';
        }
        if ('pronto' === get_post_meta($id, 'nescon_pedido_status', true)) {
            nescon_conteudo_agendar($id, $quando);

            return 'publish' === get_post_status($id) ? 'Artigo publicado (a data escolhida já tinha passado).' : 'Publicação agendada para ' . wp_date('d/m/Y \à\s H:i', $quando) . '.';
        }
        update_post_meta($id, 'nescon_publicar_em', $quando);
        nescon_pedido_registrar($id, 'Publicação marcada para ' . wp_date('d/m/Y H:i', $quando) . ', quando o texto ficar pronto');

        return 'O artigo será publicado em ' . wp_date('d/m/Y \à\s H:i', $quando) . ', assim que ficar pronto.';
    }

    if (isset($_POST['nescon_desagendar']) && check_admin_referer('nescon_desagendar_' . $id) && current_user_can('publish_post', $id)) {
        delete_post_meta($id, 'nescon_publicar_em');
        if ('future' === get_post_status($id)) {
            wp_update_post(['ID' => $id, 'post_status' => 'draft']);
        }
        nescon_pedido_registrar($id, 'Agendamento cancelado: voltou para rascunho');

        return 'Agendamento cancelado. O artigo voltou para rascunho.';
    }

    return '';
}

/** Botões de agendar, cancelar agendamento e excluir de um pedido. */
function nescon_conteudo_controles(WP_Post $p, string $situacao, bool $grande = false): string
{
    $id = $p->ID;
    $tamanho = $grande ? '' : ' button-small';
    $html = '';

    if ('publicado' !== $situacao && current_user_can('publish_post', $id)) {
        $agendado = 'agendado' === $situacao || (int) get_post_meta($id, 'nescon_publicar_em', true);
        if ($agendado) {
            $html .= '<form method="post">' . wp_nonce_field('nescon_desagendar_' . $id, '_wpnonce', true, false)
                . sprintf('<input type="hidden" name="id" value="%d"><button class="button%s" name="nescon_desagendar" value="1">Cancelar agendamento</button></form>', $id, $tamanho);
        }
        if ('erro' !== $situacao) {
            $quando = 'agendado' === $situacao ? strtotime(get_gmt_from_date($p->post_date)) : ((int) get_post_meta($id, 'nescon_publicar_em', true) ?: time() + DAY_IN_SECONDS);
            $html .= sprintf('<details class="nescon-agendar"><summary>%s</summary><form method="post">', $agendado ? 'Mudar data' : 'Agendar publicação')
                . wp_nonce_field('nescon_agendar_' . $id, '_wpnonce', true, false)
                . sprintf(
                    '<input type="hidden" name="id" value="%d"><input type="datetime-local" name="publicar_em" required value="%s" min="%s"><button class="button%s" name="nescon_agendar" value="1">Salvar</button></form></details>',
                    $id,
                    esc_attr(wp_date('Y-m-d\TH:i', $quando)),
                    esc_attr(wp_date('Y-m-d\TH:i')),
                    $tamanho,
                );
        }
    }

    if (current_user_can('delete_post', $id)) {
        $pergunta = 'publicado' === $situacao ? 'Este artigo está publicado e vai sair do site. Excluir mesmo?' : 'Excluir este pedido? O artigo vai para a lixeira de Páginas.';
        $html .= '<form method="post" onsubmit="return confirm(' . esc_attr(wp_json_encode($pergunta)) . ')">' . wp_nonce_field('nescon_excluir_' . $id, '_wpnonce', true, false)
            . sprintf('<input type="hidden" name="id" value="%d"><button class="button button-link-delete%s" name="nescon_excluir" value="1">Excluir</button></form>', $id, $tamanho);
    }

    return $html !== '' ? '<div class="nescon-controles">' . $html . '</div>' : '';
}
