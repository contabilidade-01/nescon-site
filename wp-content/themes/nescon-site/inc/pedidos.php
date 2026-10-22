<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * "Pedir artigo" no wp-admin: a equipe escreve o tema, o Claude escreve o
 * artigo no padrão do guia-de-estilo.md e deixa como rascunho em /blog. Pela
 * mesma tela dá para pedir ajustes no rascunho. Quem tem o papel "Redator(a)"
 * pede, ajusta e envia para revisão, mas não publica.
 *
 * A chave da API do Claude fica em Configurações > Claude (artigos), ou na
 * constante NESCON_CLAUDE_API_KEY do wp-config.php.
 *
 * O Claude é chamado por HTTP direto (wp_remote_post): o SDK oficial em PHP
 * depende do Composer, que não existe na hospedagem compartilhada.
 */

const NESCON_CLAUDE_MODELO = 'claude-opus-5-5';
const NESCON_PEDIDO_EVENTO = 'nescon_escrever_artigo';

/** Papel da equipe de conteúdo: pede e edita artigos, não publica. */
add_action('init', static function (): void {
    if (get_role('nescon_redator')) {
        return;
    }
    add_role('nescon_redator', 'Redator(a)', [
        'read' => true,
        'edit_pages' => true,
        'edit_others_pages' => true,
        'upload_files' => true,
    ]);
});

function nescon_claude_chave(): string
{
    if (defined('NESCON_CLAUDE_API_KEY') && NESCON_CLAUDE_API_KEY) {
        return (string) NESCON_CLAUDE_API_KEY;
    }

    return (string) get_option('nescon_claude_chave', '');
}

/* Configurações > Claude (artigos): só o administrador vê e troca a chave. */
add_action('admin_menu', static function (): void {
    add_options_page('Claude (artigos)', 'Claude (artigos)', 'manage_options', 'nescon-claude', static function (): void {
        if (isset($_POST['nescon_claude_chave']) && check_admin_referer('nescon_claude')) {
            $chave = trim(sanitize_text_field(wp_unslash($_POST['nescon_claude_chave'])));
            if ($chave !== '') {
                update_option('nescon_claude_chave', $chave, false);
            }
            echo '<div class="notice notice-success"><p>Chave salva.</p></div>';
        }
        $atual = nescon_claude_chave();
        echo '<div class="wrap"><h1>Claude (artigos)</h1><p>Chave da API do Claude usada em <strong>Pedir artigo</strong>. Crie em <a href="https://platform.claude.com" target="_blank" rel="noopener">platform.claude.com</a>, em <em>API keys</em>.</p><form method="post">';
        wp_nonce_field('nescon_claude');
        printf(
            '<p><label for="nescon_claude_chave">Chave da API</label><br><input type="password" id="nescon_claude_chave" name="nescon_claude_chave" class="regular-text" autocomplete="off" placeholder="%s"></p>',
            esc_attr($atual !== '' ? 'Configurada (termina em ' . substr($atual, -4) . '). Preencha só para trocar.' : 'sk-ant-...'),
        );
        submit_button('Salvar chave');
        echo '</form></div>';
    });
});

/* Tela "Pedir artigo". */
add_action('admin_menu', static function (): void {
    add_menu_page('Pedir artigo', 'Pedir artigo', 'edit_pages', 'nescon-pedir-artigo', 'nescon_pedidos_tela', 'dashicons-edit-large', 4);
});

function nescon_pedido_status_texto(WP_Post $p): string
{
    $status = (string) get_post_meta($p->ID, 'nescon_pedido_status', true);

    return match ($status) {
        'fila' => 'Na fila, começa em instantes',
        'escrevendo' => 'O Claude está escrevendo (leva de 2 a 5 minutos)',
        'pronto' => 'publish' === $p->post_status ? 'Publicado' : ('pending' === $p->post_status ? 'Aguardando revisão do contador' : 'Rascunho pronto'),
        'erro' => 'Não deu certo: ' . get_post_meta($p->ID, 'nescon_pedido_erro', true),
        default => '',
    };
}

function nescon_pedido_agendar(int $id, string $ajuste = ''): void
{
    update_post_meta($id, 'nescon_pedido_status', 'fila');
    update_post_meta($id, 'nescon_pedido_inicio', time());
    delete_post_meta($id, 'nescon_pedido_erro');
    wp_schedule_single_event(time(), NESCON_PEDIDO_EVENTO, [$id, $ajuste, wp_generate_password(6, false)]);
    spawn_cron();
}

function nescon_pedidos_tela(): void
{
    $blog = get_page_by_path('blog', OBJECT, 'page');
    $aviso = '';

    if (isset($_POST['nescon_pedido']) && check_admin_referer('nescon_pedido') && $blog instanceof WP_Post) {
        $tema = sanitize_text_field(wp_unslash($_POST['tema'] ?? ''));
        $orientacoes = sanitize_textarea_field(wp_unslash($_POST['orientacoes'] ?? ''));
        if ($tema !== '') {
            $id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'draft',
                'post_parent' => $blog->ID,
                'post_title' => 'Em produção: ' . $tema,
                'post_author' => get_current_user_id(),
                'meta_input' => [
                    'nescon_pedido_tema' => $tema,
                    'nescon_pedido_orientacoes' => $orientacoes,
                ],
            ]);
            nescon_pedido_agendar((int) $id);
            $aviso = 'Pedido enviado. O artigo aparece abaixo em alguns minutos.';
        }
    }

    if (isset($_POST['nescon_ajuste']) && check_admin_referer('nescon_ajuste')) {
        $id = (int) ($_POST['id'] ?? 0);
        $ajuste = sanitize_textarea_field(wp_unslash($_POST['ajuste'] ?? ''));
        $post = get_post($id);
        if ($ajuste !== '' && nescon_eh_artigo($post) && 'publish' !== $post->post_status && current_user_can('edit_post', $id)) {
            nescon_pedido_agendar($id, $ajuste);
            $aviso = 'Ajuste pedido. O rascunho é atualizado em alguns minutos.';
        }
    }

    if (isset($_POST['nescon_repetir']) && check_admin_referer('nescon_repetir')) {
        $id = (int) ($_POST['id'] ?? 0);
        if (current_user_can('edit_post', $id)) {
            nescon_pedido_agendar($id, (string) get_post_meta($id, 'nescon_pedido_ultimo_ajuste', true));
            $aviso = 'Pedido enviado de novo.';
        }
    }

    $pedidos = get_posts([
        'post_type' => 'page',
        'post_status' => ['draft', 'pending', 'publish'],
        'meta_key' => 'nescon_pedido_status',
        'numberposts' => 30,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    $andamento = array_filter($pedidos, static fn (WP_Post $p): bool => in_array(get_post_meta($p->ID, 'nescon_pedido_status', true), ['fila', 'escrevendo'], true));

    echo '<div class="wrap"><h1>Pedir artigo</h1>';
    if ($andamento) {
        // Atualiza a tela sozinha enquanto houver artigo sendo escrito.
        echo '<meta http-equiv="refresh" content="20">';
    }
    if ($aviso !== '') {
        echo '<div class="notice notice-success"><p>' . esc_html($aviso) . '</p></div>';
    }
    if (nescon_claude_chave() === '') {
        echo '<div class="notice notice-warning"><p>A chave da API do Claude ainda não foi cadastrada. O administrador cadastra em Configurações &gt; Claude (artigos).</p></div>';
    }

    echo '<div class="card" style="max-width:720px"><h2>Novo artigo</h2><p>Escreva a dúvida do cliente, como ele perguntaria. O Claude escreve no padrão do site e deixa como rascunho para o contador revisar.</p><form method="post">';
    wp_nonce_field('nescon_pedido');
    echo '<p><label for="tema"><strong>Tema do artigo</strong></label><br><input type="text" id="tema" name="tema" class="large-text" required placeholder="Ex.: Quanto o sócio deve retirar de pró-labore?"></p>';
    echo '<p><label for="orientacoes"><strong>Orientações</strong> (opcional)</label><br><textarea id="orientacoes" name="orientacoes" rows="3" class="large-text" placeholder="Ex.: falar do Fator R e dar um exemplo com números redondos."></textarea></p>';
    submit_button('Pedir artigo', 'primary', 'nescon_pedido', false);
    echo '</form></div>';

    echo '<h2 style="margin-top:2em">Pedidos</h2>';
    if (!$pedidos) {
        echo '<p>Nenhum pedido ainda.</p></div>';

        return;
    }

    echo '<table class="widefat striped"><thead><tr><th>Artigo</th><th>Situação</th><th>Ações</th></tr></thead><tbody>';
    foreach ($pedidos as $p) {
        $status = (string) get_post_meta($p->ID, 'nescon_pedido_status', true);
        $parado = in_array($status, ['fila', 'escrevendo'], true) && time() - (int) get_post_meta($p->ID, 'nescon_pedido_inicio', true) > 15 * MINUTE_IN_SECONDS;

        echo '<tr><td><strong>' . esc_html(get_the_title($p)) . '</strong><br><small>Pedido: ' . esc_html((string) get_post_meta($p->ID, 'nescon_pedido_tema', true)) . '</small></td>';
        echo '<td>' . esc_html($parado ? 'Demorou mais que o normal' : nescon_pedido_status_texto($p));
        $pontos = (array) get_post_meta($p->ID, 'nescon_pedido_pontos', true);
        if ('pronto' === $status && 'publish' !== $p->post_status && array_filter($pontos)) {
            echo '<details><summary>Pontos para o contador conferir</summary><ul style="list-style:disc;margin-left:1.5em">';
            foreach ($pontos as $ponto) {
                echo '<li>' . esc_html($ponto) . '</li>';
            }
            echo '</ul></details>';
        }
        echo '</td><td>';
        if ('pronto' === $status) {
            printf(
                '<a class="button" href="%s" target="_blank">Ver como fica</a> <a class="button" href="%s">Editar</a>',
                esc_url('publish' === $p->post_status ? get_permalink($p) : get_preview_post_link($p)),
                esc_url(get_edit_post_link($p->ID, 'url')),
            );
            if ('publish' !== $p->post_status) {
                echo '<form method="post" style="margin-top:.5em">';
                wp_nonce_field('nescon_ajuste');
                printf('<input type="hidden" name="id" value="%d"><textarea name="ajuste" rows="2" class="large-text" placeholder="O que mudar? Ex.: deixar a resposta rápida mais curta"></textarea>', $p->ID);
                submit_button('Pedir ajuste', 'secondary small', 'nescon_ajuste', false);
                echo '</form>';
            }
        } elseif ('erro' === $status || $parado) {
            echo '<form method="post">';
            wp_nonce_field('nescon_repetir');
            printf('<input type="hidden" name="id" value="%d">', $p->ID);
            submit_button('Tentar de novo', 'secondary small', 'nescon_repetir', false);
            echo '</form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

/* Pontos de revisão também na tela de edição do artigo. */
add_action('add_meta_boxes_page', static function (WP_Post $post): void {
    $pontos = array_filter((array) get_post_meta($post->ID, 'nescon_pedido_pontos', true));
    if (!$pontos || !nescon_eh_artigo($post)) {
        return;
    }
    add_meta_box('nescon-pontos', 'Conferir antes de publicar', static function () use ($pontos): void {
        echo '<ul style="list-style:disc;margin-left:1.2em">';
        foreach ($pontos as $ponto) {
            echo '<li>' . esc_html($ponto) . '</li>';
        }
        echo '</ul>';
    }, null, 'side', 'high');
});

/** Corpo, resumo e perguntas de um artigo estático do tema, usado como exemplo. */
function nescon_artigo_exemplo(string $slug): array
{
    $html = (string) file_get_contents(get_template_directory() . '/static/blog/' . $slug . '.html');
    preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $titulo);
    preg_match('#aria-label="Resposta rápida">.*?<p class="mt-2[^>]*>(.*?)</p>#s', $html, $resumo);
    preg_match('#<div class="prose[^>]*>(.*?)</div><div class="mt-12#s', $html, $corpo);
    preg_match_all('#<h3 class="text-base">(.*?)</h3>.*?<p class="mt-3[^>]*>(.*?)</p>#s', $html, $faq, PREG_SET_ORDER);

    return [
        'titulo' => html_entity_decode(wp_strip_all_tags($titulo[1] ?? '')),
        'resumo' => html_entity_decode(wp_strip_all_tags($resumo[1] ?? '')),
        'corpo_html' => trim((string) preg_replace('#<h2 id="[^"]*">#', '<h2>', $corpo[1] ?? '')),
        'faq' => array_map(static fn ($f) => ['pergunta' => html_entity_decode($f[1]), 'resposta' => html_entity_decode(wp_strip_all_tags($f[2]))], $faq),
    ];
}

/** Texto do artigo como o WordPress guarda: corpo + perguntas em blocos "Detalhes". */
function nescon_artigo_conteudo(string $corpo, array $faq): string
{
    $html = trim($corpo);
    if ($faq) {
        $html .= "\n\n<h2>Perguntas frequentes</h2>";
        foreach ($faq as $f) {
            $html .= "\n\n<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . esc_html($f['pergunta'])
                . '</summary><p>' . esc_html($f['resposta']) . "</p></details>\n<!-- /wp:details -->";
        }
    }

    return $html;
}

/** Títulos dos artigos que já existem, para o Claude não repetir tema. */
function nescon_artigos_existentes(int $ignorar): string
{
    $titulos = [];
    foreach (glob(get_template_directory() . '/static/blog/*.html') ?: [] as $arquivo) {
        $slug = basename($arquivo, '.html');
        $titulos[$slug] = '- ' . nescon_artigo_exemplo($slug)['titulo'] . " (/blog/{$slug})";
    }
    $blog = get_page_by_path('blog', OBJECT, 'page');
    if ($blog instanceof WP_Post) {
        foreach (get_pages(['parent' => $blog->ID, 'post_status' => 'publish,draft,pending']) ?: [] as $p) {
            if ($p->ID !== $ignorar && $p->post_name !== '' && trim($p->post_content) !== '') {
                $titulos[$p->post_name] ??= '- ' . $p->post_title . " (/blog/{$p->post_name})";
            }
        }
    }

    return implode("\n", $titulos);
}

/** Uma chamada à API de mensagens do Claude. */
function nescon_claude(array $corpo): array
{
    $resposta = wp_remote_post('https://api.anthropic.com/v1/messages', [
        'timeout' => 300,
        'headers' => [
            'x-api-key' => nescon_claude_chave(),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ],
        'body' => wp_json_encode($corpo),
    ]);
    if (is_wp_error($resposta)) {
        throw new RuntimeException('falha de conexão com o Claude (' . $resposta->get_error_message() . ')');
    }
    $dados = json_decode((string) wp_remote_retrieve_body($resposta), true);
    if (200 !== wp_remote_retrieve_response_code($resposta)) {
        throw new RuntimeException('o Claude recusou o pedido (' . ($dados['error']['message'] ?? 'HTTP ' . wp_remote_retrieve_response_code($resposta)) . ')');
    }

    return $dados;
}

/** Escreve (ou ajusta) o artigo do pedido e grava o rascunho. */
function nescon_escrever_artigo(int $id, string $ajuste = ''): void
{
    $post = get_post($id);
    if (!nescon_eh_artigo($post)) {
        return;
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(900);
    }
    ignore_user_abort(true);
    update_post_meta($id, 'nescon_pedido_status', 'escrevendo');
    update_post_meta($id, 'nescon_pedido_ultimo_ajuste', $ajuste);

    try {
        if (nescon_claude_chave() === '') {
            throw new RuntimeException('a chave da API do Claude não está cadastrada');
        }

        $guia = (string) file_get_contents(get_template_directory() . '/guia-de-estilo.md');
        $exemplo = wp_json_encode(['categoria' => 'Gestão', 'servico' => 'contabilidade-simples-nacional'] + nescon_artigo_exemplo(NESCON_ARTIGO_MODELO), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sistema = "Você escreve e ajusta artigos do blog da Nescon Contabilidade, escritório de contabilidade em Guarulhos (SP). Siga sempre o guia de estilo abaixo e use a busca na web (só sites gov.br) para confirmar números, prazos e regras.\n\n<guia_de_estilo>\n{$guia}\n</guia_de_estilo>\n\nArtigo publicado do site, como exemplo do padrão (mesmos campos que você deve devolver):\n<artigo_exemplo>\n{$exemplo}\n</artigo_exemplo>";

        if ($ajuste !== '' && trim($post->post_content) !== '') {
            preg_match_all('#<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>#s', $post->post_content, $m, PREG_SET_ORDER);
            $atual = wp_json_encode([
                'titulo' => $post->post_title,
                'slug' => $post->post_name,
                'resumo' => $post->post_excerpt,
                'descricao' => get_post_meta($id, 'nescon_descricao', true),
                'categoria' => get_post_meta($id, 'nescon_categoria', true),
                'servico' => get_post_meta($id, 'nescon_servico', true),
                'corpo_html' => trim((string) preg_replace(['#<!-- /?wp:details -->#', '#<details.*?</details>#s', '#<h2[^>]*>\s*Perguntas frequentes\s*</h2>#i'], '', $post->post_content)),
                'faq' => array_map(static fn ($f) => ['pergunta' => html_entity_decode(wp_strip_all_tags($f[1])), 'resposta' => html_entity_decode(trim(wp_strip_all_tags($f[2])))], $m),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pedido = "Ajuste o rascunho abaixo conforme o pedido. Mude só o que o pedido exige e mantenha o resto como está, no padrão do guia.\n\nPedido de ajuste:\n<pedido>\n{$ajuste}\n</pedido>\n\nRascunho atual:\n<rascunho>\n{$atual}\n</rascunho>";
        } else {
            $orientacoes = (string) get_post_meta($id, 'nescon_pedido_orientacoes', true);
            $pedido = 'Tema do novo artigo: ' . get_post_meta($id, 'nescon_pedido_tema', true)
                . ($orientacoes !== '' ? "\n\nOrientações de quem pediu o artigo:\n<orientacoes>\n{$orientacoes}\n</orientacoes>" : '')
                . "\n\nData de hoje: " . wp_date('d/m/Y')
                . "\n\nArtigos que já existem (não repita o tema; pode linkar para eles):\n" . nescon_artigos_existentes($id);
        }

        $esquema = [
            'type' => 'object',
            'properties' => [
                'titulo' => ['type' => 'string', 'description' => 'Título do artigo'],
                'slug' => ['type' => 'string', 'description' => 'Endereço curto: letras minúsculas sem acento, números e hífens'],
                'resumo' => ['type' => 'string', 'description' => 'Resposta rápida: 2 ou 3 frases que respondem ao título'],
                'descricao' => ['type' => 'string', 'description' => 'Descrição para o Google, até 170 caracteres'],
                'categoria' => ['type' => 'string', 'enum' => nescon_artigo_categorias()],
                'servico' => ['type' => 'string', 'enum' => array_keys(nescon_artigo_servicos()), 'description' => 'Serviço chamado no fim do artigo'],
                'corpo_html' => ['type' => 'string', 'description' => 'Corpo do artigo em HTML, só com as tags permitidas no guia, terminando com a nota de revisão'],
                'faq' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['pergunta' => ['type' => 'string'], 'resposta' => ['type' => 'string']],
                        'required' => ['pergunta', 'resposta'],
                        'additionalProperties' => false,
                    ],
                ],
                'pontos_de_revisao' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Números, prazos e afirmações que o contador deve conferir antes de publicar, com a fonte usada',
                ],
            ],
            'required' => ['titulo', 'slug', 'resumo', 'descricao', 'categoria', 'servico', 'corpo_html', 'faq', 'pontos_de_revisao'],
            'additionalProperties' => false,
        ];

        $mensagens = [['role' => 'user', 'content' => $pedido]];
        for ($i = 0; ; ++$i) {
            $resposta = nescon_claude([
                'model' => NESCON_CLAUDE_MODELO,
                'max_tokens' => 16000,
                'system' => $sistema,
                'messages' => $mensagens,
                'tools' => [['type' => 'web_search_20260209', 'name' => 'web_search', 'allowed_domains' => ['gov.br'], 'max_uses' => 8]],
                'output_config' => ['effort' => 'high', 'format' => ['type' => 'json_schema', 'schema' => $esquema]],
            ]);
            // A busca na web roda no servidor do Claude; uma pausa só pede para continuar.
            if ('pause_turn' === ($resposta['stop_reason'] ?? '') && $i < 5) {
                $mensagens = [['role' => 'user', 'content' => $pedido], ['role' => 'assistant', 'content' => $resposta['content']]];

                continue;
            }
            break;
        }

        if ('end_turn' !== ($resposta['stop_reason'] ?? '')) {
            throw new RuntimeException('o Claude não concluiu o artigo (' . ($resposta['stop_reason'] ?? 'sem motivo') . ')');
        }
        $texto = '';
        foreach ($resposta['content'] as $bloco) {
            if ('text' === $bloco['type']) {
                $texto = $bloco['text'];
            }
        }
        $artigo = json_decode($texto, true);
        if (!is_array($artigo) || empty($artigo['corpo_html'])) {
            throw new RuntimeException('a resposta do Claude veio incompleta');
        }

        $slug = sanitize_title($artigo['slug']);
        wp_update_post(wp_slash([
            'ID' => $id,
            'post_title' => $artigo['titulo'],
            'post_name' => wp_unique_post_slug($slug, $id, 'publish', 'page', $post->post_parent),
            'post_excerpt' => $artigo['resumo'],
            'post_content' => nescon_artigo_conteudo(wp_kses_post($artigo['corpo_html']), $artigo['faq']),
        ]));
        update_post_meta($id, 'nescon_descricao', mb_substr(sanitize_text_field($artigo['descricao']), 0, 170));
        update_post_meta($id, 'nescon_categoria', sanitize_text_field($artigo['categoria']));
        update_post_meta($id, 'nescon_servico', sanitize_key($artigo['servico']));
        update_post_meta($id, 'nescon_pedido_pontos', array_map('sanitize_text_field', $artigo['pontos_de_revisao']));
        update_post_meta($id, 'nescon_pedido_status', 'pronto');
    } catch (Throwable $e) {
        update_post_meta($id, 'nescon_pedido_status', 'erro');
        update_post_meta($id, 'nescon_pedido_erro', $e->getMessage());
    }
}

add_action(NESCON_PEDIDO_EVENTO, 'nescon_escrever_artigo', 10, 2);

/* Mantém as caixinhas do "Checklist rápido" quando quem salva não é administrador. */
add_filter('wp_kses_allowed_html', static function (array $tags, $contexto): array {
    if ('post' === $contexto) {
        $tags['input'] = ['type' => true, 'disabled' => true, 'checked' => true];
    }

    return $tags;
}, 10, 2);
