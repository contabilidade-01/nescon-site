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

/*
 * No LiteSpeed (Hostinger), o PHP de uma tarefa agendada é encerrado quando a
 * requisição que a disparou se desconecta, o que deixava o pedido parado em
 * "escrevendo". A variável noabort, recomendada pelo LiteSpeed para o
 * WP-Cron, deixa a tarefa terminar.
 */
add_action('admin_init', static function (): void {
    if (get_option('nescon_noabort') === '1' || !is_writable(ABSPATH . '.htaccess')) {
        return;
    }
    if (!function_exists('insert_with_markers')) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }
    if (insert_with_markers(ABSPATH . '.htaccess', 'Nescon LiteSpeed', ['<IfModule Litespeed>', 'SetEnv noabort 1', '</IfModule>'])) {
        update_option('nescon_noabort', '1');
    }
});

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

function nescon_openai_chave(): string
{
    if (defined('NESCON_OPENAI_API_KEY') && NESCON_OPENAI_API_KEY) {
        return (string) NESCON_OPENAI_API_KEY;
    }

    return (string) get_option('nescon_openai_chave', '');
}

function nescon_claude_chave(): string
{
    if (defined('NESCON_CLAUDE_API_KEY') && NESCON_CLAUDE_API_KEY) {
        return (string) NESCON_CLAUDE_API_KEY;
    }

    return (string) get_option('nescon_claude_chave', '');
}

/**
 * Confere se cada chave cadastrada conecta na respectiva API, sem gerar nada
 * (consulta só a lista de modelos, que não tem custo).
 */
function nescon_testar_chaves(): array
{
    $testes = [
        'Claude' => [nescon_claude_chave(), 'https://api.anthropic.com/v1/models/' . NESCON_CLAUDE_MODELO, static fn (string $chave): array => ['x-api-key' => $chave, 'anthropic-version' => '2023-06-01']],
        'OpenAI (imagens)' => [nescon_openai_chave(), 'https://api.openai.com/v1/models/gpt-image-1', static fn (string $chave): array => ['Authorization' => 'Bearer ' . $chave]],
    ];
    $resultado = [];
    foreach ($testes as $nome => [$chave, $url, $cabecalhos]) {
        if ($chave === '') {
            $resultado[$nome] = [false, 'chave não cadastrada'];
            continue;
        }
        $resposta = wp_remote_get($url, ['timeout' => 20, 'headers' => $cabecalhos($chave)]);
        if (is_wp_error($resposta)) {
            $resultado[$nome] = [false, 'o site não conseguiu se conectar (' . $resposta->get_error_message() . ')'];
            continue;
        }
        $codigo = (int) wp_remote_retrieve_response_code($resposta);
        $erro = json_decode((string) wp_remote_retrieve_body($resposta), true)['error']['message'] ?? '';
        $resultado[$nome] = match (true) {
            200 === $codigo => [true, 'conectado'],
            401 === $codigo => [false, 'chave inválida ou revogada'],
            403 === $codigo => [false, 'a chave não tem acesso a este modelo' . ($erro !== '' ? " ({$erro})" : '')],
            404 === $codigo => [false, 'modelo não disponível para esta conta' . ($erro !== '' ? " ({$erro})" : '')],
            429 === $codigo => [false, 'limite de uso ou créditos esgotados' . ($erro !== '' ? " ({$erro})" : '')],
            default => [false, "erro HTTP {$codigo}" . ($erro !== '' ? " ({$erro})" : '')],
        };
    }

    return $resultado;
}

/* Configurações > Claude (artigos): só o administrador vê e troca a chave. */
add_action('admin_menu', static function (): void {
    add_options_page('Claude (artigos)', 'Claude (artigos)', 'manage_options', 'nescon-claude', static function (): void {
        if (isset($_POST['nescon_claude_chave']) && !isset($_POST['nescon_testar']) && check_admin_referer('nescon_claude')) {
            foreach (['nescon_claude_chave', 'nescon_openai_chave'] as $campo) {
                $chave = trim(sanitize_text_field(wp_unslash($_POST[$campo] ?? '')));
                if ($chave !== '') {
                    update_option($campo, $chave, false);
                }
            }
            echo '<div class="notice notice-success"><p>Chaves salvas.</p></div>';
        }
        // Ao salvar ou ao clicar em "Testar conexão", confere as duas chaves.
        if ((isset($_POST['nescon_claude_chave']) || isset($_POST['nescon_testar'])) && check_admin_referer('nescon_claude')) {
            foreach (nescon_testar_chaves() as $nome => [$ok, $mensagem]) {
                printf(
                    '<div class="notice notice-%s"><p><strong>%s:</strong> %s</p></div>',
                    $ok ? 'success' : 'error',
                    esc_html($nome),
                    esc_html(($ok ? '✓ ' : '✗ ') . $mensagem),
                );
            }
        }
        $atual = nescon_claude_chave();
        $openai = nescon_openai_chave();
        echo '<div class="wrap"><h1>Claude (artigos)</h1><p>Chave da API do Claude usada em <strong>Pedir artigo</strong>. Crie em <a href="https://platform.claude.com" target="_blank" rel="noopener">platform.claude.com</a>, em <em>API keys</em>.</p><form method="post">';
        wp_nonce_field('nescon_claude');
        printf(
            '<p><label for="nescon_claude_chave">Chave da API</label><br><input type="password" id="nescon_claude_chave" name="nescon_claude_chave" class="regular-text" autocomplete="off" placeholder="%s"></p>',
            esc_attr($atual !== '' ? 'Configurada (termina em ' . substr($atual, -4) . '). Preencha só para trocar.' : 'sk-ant-...'),
        );
        printf(
            '<p><label for="nescon_openai_chave">Chave da API da OpenAI (imagens)</label><br><input type="password" id="nescon_openai_chave" name="nescon_openai_chave" class="regular-text" autocomplete="off" placeholder="%s"><br><span class="description">Opcional. Com ela, cada artigo ganha uma ilustração nova no estilo do site. Crie em platform.openai.com, em <em>API keys</em>. Sem ela, o artigo usa a foto do serviço relacionado.</span></p>',
            esc_attr($openai !== '' ? 'Configurada (termina em ' . substr($openai, -4) . '). Preencha só para trocar.' : 'sk-...'),
        );
        echo '<p class="submit">';
        submit_button('Salvar chaves', 'primary', 'submit', false);
        echo ' ';
        submit_button('Testar conexão', 'secondary', 'nescon_testar', false);
        echo '</p></form>';
        $visto = (int) get_option('nescon_tarefas_visto', 0);
        echo '<h2>Cron do servidor</h2>';
        if (nescon_cron_servidor()) {
            printf('<p>✓ Ativo. Última execução às %s. Os artigos são escritos sem o limite de tempo da hospedagem.</p>', esc_html(wp_date('H:i:s', $visto)));
        } else {
            echo '<p>' . ($visto ? '✗ Parado desde ' . esc_html(wp_date('d/m H:i', $visto)) . '.' : '✗ Não configurado.') . ' Sem ele, as tarefas rodam pelo site, que a hospedagem corta em cerca de 2 minutos: o Claude pesquisa e pensa menos para caber nesse tempo.</p>';
        }
        printf(
            '<p>Na Hostinger: hPanel &gt; Avançado &gt; Cron Jobs, tipo PHP, a cada minuto, com o comando:</p><p><code>%s</code></p>',
            esc_html('php ' . get_template_directory() . '/tarefas.php'),
        );
        echo '</div>';
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
        'ilustrando' => 'Texto pronto. A OpenAI está criando a imagem (cerca de 1 minuto)',
        'pronto' => 'publish' === $p->post_status ? 'Publicado' : ('pending' === $p->post_status ? 'Aguardando revisão do contador' : 'Rascunho pronto'),
        'erro' => 'Não deu certo: ' . get_post_meta($p->ID, 'nescon_pedido_erro', true),
        default => '',
    };
}

/**
 * Registro do andamento de cada pedido, mostrado ao vivo na tela "Pedir
 * artigo": dá para ver se o Claude e a OpenAI estão trabalhando ou se a
 * tarefa parou.
 */
function nescon_pedido_registrar(int $id, string $texto, bool $novo = false): void
{
    $registro = $novo ? [] : (array) get_post_meta($id, 'nescon_pedido_registro', true);
    $registro[] = [time(), $texto];
    update_post_meta($id, 'nescon_pedido_registro', array_slice(array_filter($registro), -60));
    delete_post_meta($id, 'nescon_pedido_agora');
}

/** O que está acontecendo neste instante (ex.: quantas palavras já foram escritas). */
function nescon_pedido_agora(int $id, string $texto): void
{
    update_post_meta($id, 'nescon_pedido_agora', [time(), $texto]);
}

function nescon_pedido_registro_html(int $id): string
{
    $registro = array_filter((array) get_post_meta($id, 'nescon_pedido_registro', true));
    if (!$registro) {
        return '';
    }
    $html = '<ol class="nescon-registro">';
    foreach ($registro as [$quando, $texto]) {
        $html .= '<li><time>' . esc_html(wp_date('H:i:s', (int) $quando)) . '</time> ' . esc_html($texto) . '</li>';
    }
    $status = (string) get_post_meta($id, 'nescon_pedido_status', true);
    $ultima = (int) end($registro)[0];
    $agora = get_post_meta($id, 'nescon_pedido_agora', true);
    if (is_array($agora) && in_array($status, ['escrevendo', 'ilustrando'], true)) {
        $html .= '<li class="agora"><time>' . esc_html(wp_date('H:i:s', (int) $agora[0])) . '</time> ' . esc_html($agora[1]) . '</li>';
        $ultima = max($ultima, (int) $agora[0]);
    }
    $html .= '</ol>';
    if (in_array($status, ['fila', 'escrevendo', 'ilustrando'], true)) {
        $html .= sprintf('<p class="nescon-ativo" data-desde="%d">Última atividade há <span>…</span></p>', $ultima);
    }

    return $html;
}

/* A tela consulta o andamento a cada 5 segundos, sem recarregar a página. */
add_action('wp_ajax_nescon_pedido_registro', static function (): void {
    check_ajax_referer('nescon_pedido_registro');
    $resposta = [];
    foreach (array_map('intval', (array) ($_GET['ids'] ?? [])) as $id) {
        if (current_user_can('edit_post', $id)) {
            $resposta[$id] = ['status' => (string) get_post_meta($id, 'nescon_pedido_status', true), 'html' => nescon_pedido_registro_html($id)];
        }
    }
    wp_send_json($resposta);
});

/*
 * Quem roda as tarefas. Disparadas pelo site (WP-Cron), elas são cortadas
 * pela hospedagem em cerca de 2 minutos. Pelo cron do servidor (hPanel >
 * Cron Jobs, rodando tarefas.php a cada minuto) não há esse corte; quando ele
 * está ativo, o site deixa as tarefas para ele.
 */
function nescon_cron_servidor(): bool
{
    return time() - (int) get_option('nescon_tarefas_visto', 0) < 5 * MINUTE_IN_SECONDS;
}

function nescon_pedido_enfileirar(int $id, array $tarefa, string $texto, bool $novo = false): void
{
    update_post_meta($id, 'nescon_pedido_status', 'fila');
    update_post_meta($id, 'nescon_pedido_inicio', time());
    update_post_meta($id, 'nescon_pedido_tarefa', $tarefa);
    if (nescon_cron_servidor()) {
        nescon_pedido_registrar($id, $texto . ', esperando o cron do servidor (até 1 minuto)', $novo);

        return;
    }
    nescon_pedido_registrar($id, $texto . ', esperando o agendador do WordPress', $novo);
    if ('imagem' === $tarefa[0]) {
        wp_schedule_single_event(time(), 'nescon_refazer_imagem', [$id, wp_generate_password(6, false)]);
    } else {
        wp_schedule_single_event(time(), NESCON_PEDIDO_EVENTO, [$id, $tarefa[1], wp_generate_password(6, false)]);
    }
    spawn_cron();
}

function nescon_pedido_agendar(int $id, string $ajuste = ''): void
{
    delete_post_meta($id, 'nescon_pedido_erro');
    nescon_pedido_enfileirar($id, ['texto', $ajuste], $ajuste !== '' ? 'Ajuste na fila' : 'Pedido na fila', true);
}

/**
 * A imagem roda numa tarefa separada da escrita, para cada uma caber no tempo
 * que a hospedagem dá a uma tarefa.
 */
function nescon_pedido_agendar_imagem(int $id): void
{
    nescon_pedido_enfileirar($id, ['imagem'], 'Ilustração na fila');
}

/** Roda as tarefas na fila. Chamado por tarefas.php, no cron do servidor. */
function nescon_tarefas_executar(): void
{
    update_option('nescon_tarefas_visto', time(), false);
    // Uma execução por vez: o cron roda a cada minuto e um artigo leva alguns.
    $trava = (int) get_option('nescon_tarefas_trava', 0);
    if ($trava && time() - $trava < 20 * MINUTE_IN_SECONDS) {
        return;
    }
    update_option('nescon_tarefas_trava', time(), false);
    try {
        for ($n = 0; $n < 10; ++$n) {
            $fila = get_posts([
                'post_type' => 'page',
                'post_status' => 'any',
                'meta_key' => 'nescon_pedido_status',
                'meta_value' => 'fila',
                'numberposts' => 1,
                'orderby' => 'modified',
                'order' => 'ASC',
            ]);
            if (!$fila) {
                break;
            }
            $tarefa = (array) get_post_meta($fila[0]->ID, 'nescon_pedido_tarefa', true) + ['texto', ''];
            if ('imagem' === $tarefa[0]) {
                nescon_ilustrar_artigo($fila[0]->ID);
            } else {
                nescon_escrever_artigo($fila[0]->ID, (string) $tarefa[1]);
            }
            update_option('nescon_tarefas_visto', time(), false);
        }
    } finally {
        delete_option('nescon_tarefas_trava');
    }
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

    if (isset($_POST['nescon_nova_imagem']) && check_admin_referer('nescon_nova_imagem')) {
        $id = (int) ($_POST['id'] ?? 0);
        $cena = sanitize_textarea_field(wp_unslash($_POST['cena'] ?? ''));
        if (current_user_can('edit_post', $id) && nescon_openai_chave() !== '') {
            if ($cena !== '') {
                update_post_meta($id, 'nescon_imagem_cena', $cena);
            }
            nescon_pedido_registrar($id, 'Pedida uma imagem nova', true);
            nescon_pedido_agendar_imagem($id);
            $aviso = 'Imagem nova pedida. Aparece em cerca de um minuto.';
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
    $andamento = array_filter($pedidos, static fn (WP_Post $p): bool => in_array(get_post_meta($p->ID, 'nescon_pedido_status', true), ['fila', 'escrevendo', 'ilustrando'], true));

    echo '<div class="wrap"><h1>Pedir artigo</h1>';
    echo '<style>.nescon-registro{margin:.5em 0 0 1.5em;font-size:12px;color:#50575e}.nescon-registro li{margin:0}.nescon-registro time{font-variant-numeric:tabular-nums;color:#8c8f94}.nescon-registro .agora{color:#2271b1;font-style:italic}.nescon-ativo{margin:.3em 0 0;font-size:12px;color:#2271b1}.nescon-ativo.parado{color:#b32d2e}</style>';
    if ($andamento) {
        // Sem JavaScript, a tela ainda se atualiza sozinha a cada 20 segundos.
        echo '<noscript><meta http-equiv="refresh" content="20"></noscript>';
        nescon_pedidos_script(array_map(static fn (WP_Post $p): int => $p->ID, array_values($andamento)));
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
        $parado = in_array($status, ['fila', 'escrevendo', 'ilustrando'], true) && time() - (int) get_post_meta($p->ID, 'nescon_pedido_inicio', true) > 10 * MINUTE_IN_SECONDS;

        echo '<tr><td><strong>' . esc_html(get_the_title($p)) . '</strong><br><small>Pedido: ' . esc_html((string) get_post_meta($p->ID, 'nescon_pedido_tema', true)) . '</small></td>';
        echo '<td>' . esc_html($parado ? 'Demorou mais que o normal' : nescon_pedido_status_texto($p));
        $aviso_pedido = (string) get_post_meta($p->ID, 'nescon_pedido_aviso', true);
        if ('pronto' === $status && $aviso_pedido !== '') {
            echo '<br><small style="color:#b32d2e">' . esc_html($aviso_pedido) . '</small>';
        }
        $registro = nescon_pedido_registro_html($p->ID);
        if ($registro !== '') {
            $aberto = in_array($status, ['fila', 'escrevendo', 'ilustrando', 'erro'], true) ? ' open' : '';
            printf('<details%s><summary>Acompanhar</summary><div id="nescon-registro-%d">%s</div></details>', $aberto, $p->ID, $registro);
        }
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
                if (nescon_openai_chave() !== '') {
                    echo '<details style="margin-top:.5em"><summary>Gerar outra imagem</summary><form method="post">';
                    wp_nonce_field('nescon_nova_imagem');
                    printf(
                        '<input type="hidden" name="id" value="%d"><textarea name="cena" rows="2" class="large-text" placeholder="%s"></textarea>',
                        $p->ID,
                        esc_attr('Opcional: descreva a cena. Atual: ' . get_post_meta($p->ID, 'nescon_imagem_cena', true)),
                    );
                    submit_button('Gerar outra imagem', 'secondary small', 'nescon_nova_imagem', false);
                    echo '</form></details>';
                }
            }
        } elseif ('erro' === $status || $parado) {
            echo '<form method="post">';
            wp_nonce_field('nescon_repetir');
            printf('<input type="hidden" name="id" value="%d">', $p->ID);
            submit_button('Tentar de novo', 'secondary small', 'nescon_repetir', false);
            echo '</form>';
            // Texto já escrito: dá para ver o rascunho e gerar só a imagem, sem reescrever.
            if (trim($p->post_content) !== '') {
                printf('<p><a class="button" href="%s">Editar o texto</a></p>', esc_url(get_edit_post_link($p->ID, 'url')));
                if (nescon_openai_chave() !== '') {
                    echo '<form method="post">';
                    wp_nonce_field('nescon_nova_imagem');
                    printf('<input type="hidden" name="id" value="%d">', $p->ID);
                    submit_button('Gerar só a imagem', 'secondary small', 'nescon_nova_imagem', false);
                    echo '</form>';
                }
            }
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

/** Atualiza o andamento dos pedidos em produção e recarrega a tela quando um termina. */
function nescon_pedidos_script(array $ids): void
{
    $config = wp_json_encode([
        'url' => add_query_arg(['action' => 'nescon_pedido_registro', '_ajax_nonce' => wp_create_nonce('nescon_pedido_registro'), 'ids' => $ids], admin_url('admin-ajax.php')),
    ]);
    echo <<<HTML
<script>
(function (config) {
  function relogio() {
    document.querySelectorAll('.nescon-ativo').forEach(function (el) {
      var s = Math.max(0, Math.round(Date.now() / 1000 - el.dataset.desde));
      el.querySelector('span').textContent = s < 60 ? s + ' s' : Math.floor(s / 60) + ' min ' + (s % 60) + ' s';
      el.classList.toggle('parado', s > 360);
    });
  }
  function consultar() {
    fetch(config.url, {credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (dados) {
      var terminou = false;
      Object.keys(dados).forEach(function (id) {
        var caixa = document.getElementById('nescon-registro-' + id);
        if (caixa) { caixa.innerHTML = dados[id].html; }
        if (['fila', 'escrevendo', 'ilustrando'].indexOf(dados[id].status) < 0) { terminou = true; }
      });
      if (terminou) { location.reload(); } else { relogio(); }
    }).catch(function () {});
  }
  relogio();
  setInterval(relogio, 1000);
  setInterval(consultar, 5000);
})({$config});
</script>
HTML;
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
    preg_match('#<img[^>]*fetchpriority="high"[^>]*>#', $html, $img);
    preg_match('#alt="([^"]*)"#', $img[0] ?? '', $alt);

    return [
        'titulo' => html_entity_decode(wp_strip_all_tags($titulo[1] ?? '')),
        'resumo' => html_entity_decode(wp_strip_all_tags($resumo[1] ?? '')),
        'corpo_html' => trim((string) preg_replace('#<h2 id="[^"]*">#', '<h2>', $corpo[1] ?? '')),
        'faq' => array_map(static fn ($f) => ['pergunta' => html_entity_decode($f[1]), 'resposta' => html_entity_decode(wp_strip_all_tags($f[2]))], $faq),
        'imagem_alt' => html_entity_decode($alt[1] ?? ''),
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

/**
 * Uma chamada à API de mensagens do Claude, recebida aos poucos (streaming).
 * Cada pesquisa no gov.br e o andamento da escrita vão para o registro do
 * pedido enquanto o Claude trabalha. Usa o cURL do PHP direto porque o
 * wp_remote_post só devolve a resposta inteira no fim.
 */
function nescon_claude(array $corpo, int $id): array
{
    $mensagem = [];
    $blocos = [];
    $parcial = [];
    $erro = '';
    $buffer = '';
    $escrito = 0;
    $avisado = 0;

    $evento = static function (array $e) use (&$mensagem, &$blocos, &$parcial, &$erro, &$escrito, &$avisado, $id): void {
        $i = $e['index'] ?? 0;
        switch ($e['type'] ?? '') {
            case 'message_start':
                $mensagem = $e['message'];
                break;
            case 'content_block_start':
                $blocos[$i] = $e['content_block'];
                if (in_array($blocos[$i]['type'], ['server_tool_use', 'tool_use'], true)) {
                    $parcial[$i] = '';
                } elseif ('thinking' === $blocos[$i]['type']) {
                    nescon_pedido_agora($id, 'Claude planejando o artigo');
                } elseif ('web_search_tool_result' === $blocos[$i]['type']) {
                    $achados = $blocos[$i]['content'] ?? [];
                    if (isset($achados['error_code'])) {
                        nescon_pedido_registrar($id, 'A pesquisa falhou (' . $achados['error_code'] . ')');
                    } else {
                        $sites = array_unique(array_map(static fn ($r) => (string) wp_parse_url($r['url'] ?? '', PHP_URL_HOST), $achados));
                        nescon_pedido_registrar($id, sprintf('Encontrou %d páginas: %s', count($achados), implode(', ', array_slice(array_filter($sites), 0, 4))));
                    }
                }
                break;
            case 'content_block_delta':
                $d = $e['delta'];
                match ($d['type']) {
                    'text_delta' => $blocos[$i]['text'] .= $d['text'],
                    'thinking_delta' => $blocos[$i]['thinking'] .= $d['thinking'],
                    'signature_delta' => $blocos[$i]['signature'] = $d['signature'],
                    'input_json_delta' => $parcial[$i] .= $d['partial_json'],
                    'citations_delta' => $blocos[$i]['citations'][] = $d['citation'],
                    default => null,
                };
                if ('text_delta' === $d['type']) {
                    $escrito += strlen($d['text']);
                    if (time() - $avisado >= 5) {
                        $avisado = time();
                        nescon_pedido_agora($id, sprintf('Claude escrevendo o texto: cerca de %d palavras até agora', $escrito / 7));
                    }
                }
                break;
            case 'content_block_stop':
                if (isset($parcial[$i])) {
                    // A entrada pode vir em partes (input_json_delta) ou inteira já no início do bloco.
                    $entrada = $parcial[$i] !== '' ? json_decode($parcial[$i], true) : (array) ($blocos[$i]['input'] ?? []);
                    $blocos[$i]['input'] = $entrada ?: new stdClass();
                    if ('web_search' === ($blocos[$i]['name'] ?? '')) {
                        nescon_pedido_registrar($id, 'Pesquisando em sites oficiais: "' . ($entrada['query'] ?? '') . '"');
                    }
                }
                break;
            case 'message_delta':
                $mensagem['stop_reason'] = $e['delta']['stop_reason'] ?? null;
                break;
            case 'error':
                $erro = (string) ($e['error']['message'] ?? 'erro durante a resposta');
                break;
        }
    };

    $ch = curl_init(defined('NESCON_CLAUDE_URL') ? NESCON_CLAUDE_URL : 'https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . nescon_claude_chave(),
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => wp_json_encode($corpo + ['stream' => true]),
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 900,
        CURLOPT_CAINFO => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
        CURLOPT_WRITEFUNCTION => static function ($ch, string $pedaco) use (&$buffer, $evento): int {
            $buffer .= str_replace("\r\n", "\n", $pedaco);
            while (false !== ($fim = strpos($buffer, "\n\n"))) {
                $bloco = substr($buffer, 0, $fim);
                $buffer = substr($buffer, $fim + 2);
                foreach (explode("\n", $bloco) as $linha) {
                    if (str_starts_with($linha, 'data:')) {
                        $dados = json_decode(trim(substr($linha, 5)), true);
                        if (is_array($dados)) {
                            $evento($dados);
                        }
                    }
                }
            }

            return strlen($pedaco);
        },
    ]);
    $ok = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $falha = curl_error($ch);
    curl_close($ch);

    if (false === $ok) {
        throw new RuntimeException('falha de conexão com o Claude (' . $falha . ')');
    }
    if (200 !== $codigo) {
        // Erro antes do streaming começar vem como JSON comum.
        $dados = json_decode($buffer, true);
        throw new RuntimeException('o Claude recusou o pedido (' . ($dados['error']['message'] ?? 'HTTP ' . $codigo) . ')');
    }
    if ($erro !== '') {
        throw new RuntimeException('o Claude parou no meio (' . $erro . ')');
    }
    ksort($blocos);
    $mensagem['content'] = array_values($blocos);

    return $mensagem;
}

/** Escreve (ou ajusta) o artigo do pedido e grava o rascunho. */
function nescon_escrever_artigo(int $id, string $ajuste = ''): void
{
    $post = get_post($id);
    // Só pega pedido na fila: evita rodar duas vezes o mesmo pedido.
    if (!nescon_eh_artigo($post) || 'fila' !== get_post_meta($id, 'nescon_pedido_status', true)) {
        return;
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(900);
    }
    ignore_user_abort(true);
    update_post_meta($id, 'nescon_pedido_status', 'escrevendo');
    update_post_meta($id, 'nescon_pedido_ultimo_ajuste', $ajuste);
    // Pelo site, a hospedagem corta a tarefa em cerca de 2 minutos: o Claude pesquisa menos e pensa menos.
    $cli = 'cli' === PHP_SAPI;
    nescon_pedido_registrar($id, $cli ? 'Tarefa começou pelo cron do servidor (sem limite de tempo)' : 'Tarefa começou pelo site (a hospedagem corta em cerca de 2 minutos)');
    nescon_pedido_vigiar($id);

    try {
        if (nescon_claude_chave() === '') {
            throw new RuntimeException('a chave da API do Claude não está cadastrada');
        }

        $guia = (string) file_get_contents(get_template_directory() . '/guia-de-estilo.md');
        $exemplo = wp_json_encode(['categoria' => 'Gestão', 'servico' => 'contabilidade-simples-nacional'] + nescon_artigo_exemplo(NESCON_ARTIGO_MODELO), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sistema = "Você escreve e ajusta artigos do blog da Nescon Contabilidade, escritório de contabilidade em Guarulhos (SP). Siga sempre o guia de estilo abaixo e use a busca na web (só sites oficiais gov.br e leg.br) para confirmar a situação atual das normas, números, prazos e regras.\n\n<guia_de_estilo>\n{$guia}\n</guia_de_estilo>\n\nArtigo publicado do site, como exemplo do padrão (mesmos campos que você deve devolver):\n<artigo_exemplo>\n{$exemplo}\n</artigo_exemplo>";

        if ($ajuste !== '' && trim($post->post_content) !== '') {
            preg_match_all('#<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>#s', $post->post_content, $m, PREG_SET_ORDER);
            $atual = wp_json_encode([
                'titulo' => $post->post_title,
                'slug' => $post->post_name,
                'resumo' => $post->post_excerpt,
                'descricao' => get_post_meta($id, 'nescon_descricao', true),
                'categoria' => get_post_meta($id, 'nescon_categoria', true),
                'servico' => get_post_meta($id, 'nescon_servico', true),
                'chamada_titulo' => get_post_meta($id, 'nescon_chamada_titulo', true),
                'chamada_texto' => get_post_meta($id, 'nescon_chamada_texto', true),
                'corpo_html' => trim((string) preg_replace(['#<!-- /?wp:details -->#', '#<details.*?</details>#s', '#<h2[^>]*>\s*Perguntas frequentes\s*</h2>#i'], '', $post->post_content)),
                'faq' => array_map(static fn ($f) => ['pergunta' => html_entity_decode(wp_strip_all_tags($f[1])), 'resposta' => html_entity_decode(trim(wp_strip_all_tags($f[2])))], $m),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pedido = "Ajuste o rascunho abaixo conforme o pedido. Mude só o que o pedido exige e mantenha o resto como está, no padrão do guia. Data de hoje: " . wp_date('d/m/Y') . ".\n\nPedido de ajuste:\n<pedido>\n{$ajuste}\n</pedido>\n\nRascunho atual:\n<rascunho>\n{$atual}\n</rascunho>";
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
                'descricao' => ['type' => 'string', 'description' => 'Descrição para o Google, até 155 caracteres, com a palavra-chave e uma informação concreta'],
                'categoria' => ['type' => 'string', 'enum' => nescon_artigo_categorias()],
                'servico' => ['type' => 'string', 'enum' => array_keys(nescon_artigo_servicos()), 'description' => 'Serviço chamado no fim do artigo'],
                'chamada_titulo' => ['type' => 'string', 'description' => 'Título da chamada do fim do artigo: pergunta curta ligada ao problema do leitor'],
                'chamada_texto' => ['type' => 'string', 'description' => '1 ou 2 frases sobre como a Nescon ajuda nesse caso'],
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
                'imagem_cena' => ['type' => 'string', 'description' => 'Cena da ilustração do artigo, conforme o guia: pessoas, ambiente, ação e objetos que remetem ao tema'],
                'imagem_alt' => ['type' => 'string', 'description' => 'Texto alternativo da ilustração, descrevendo objetivamente a cena'],
                'pontos_de_revisao' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Números, prazos e afirmações que o contador deve conferir antes de publicar, com a fonte usada',
                ],
            ],
            'required' => ['titulo', 'slug', 'resumo', 'descricao', 'categoria', 'servico', 'chamada_titulo', 'chamada_texto', 'corpo_html', 'faq', 'imagem_cena', 'imagem_alt', 'pontos_de_revisao'],
            'additionalProperties' => false,
        ];

        $mensagens = [['role' => 'user', 'content' => $pedido]];
        for ($i = 0; ; ++$i) {
            nescon_pedido_registrar($id, 0 === $i ? 'Pedido enviado ao Claude' : 'Claude continuando o trabalho');
            $inicio = time();
            $resposta = nescon_claude([
                'model' => NESCON_CLAUDE_MODELO,
                'max_tokens' => 16000,
                'system' => $sistema,
                'messages' => $mensagens,
                'tools' => [['type' => 'web_search_20260209', 'name' => 'web_search', 'allowed_domains' => ['gov.br', 'leg.br'], 'max_uses' => $cli ? 8 : 3]],
                'output_config' => ['effort' => $cli ? 'high' : 'medium', 'format' => ['type' => 'json_schema', 'schema' => $esquema]],
            ], $id);
            nescon_pedido_registrar($id, sprintf('Claude terminou esta etapa em %d s', time() - $inicio));
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
        update_post_meta($id, 'nescon_chamada_titulo', sanitize_text_field($artigo['chamada_titulo']));
        update_post_meta($id, 'nescon_chamada_texto', sanitize_text_field($artigo['chamada_texto']));
        update_post_meta($id, 'nescon_categoria', sanitize_text_field($artigo['categoria']));
        update_post_meta($id, 'nescon_servico', sanitize_key($artigo['servico']));
        update_post_meta($id, 'nescon_pedido_pontos', array_map('sanitize_text_field', $artigo['pontos_de_revisao']));
        update_post_meta($id, 'nescon_imagem_cena', sanitize_text_field($artigo['imagem_cena']));
        update_post_meta($id, 'nescon_imagem_alt', sanitize_text_field($artigo['imagem_alt']));
        delete_post_meta($id, 'nescon_pedido_aviso');
        nescon_pedido_registrar($id, sprintf('Texto salvo no rascunho (%d palavras)', str_word_count(wp_strip_all_tags($artigo['corpo_html']), 0, 'ÁÉÍÓÚÂÊÔÃÕÇáéíóúâêôãõç')));

        // Ilustração nova só quando o artigo ainda não tem imagem (ou foi pedida outra).
        if (nescon_openai_chave() !== '' && (!has_post_thumbnail($id) || get_post_meta($id, 'nescon_nova_imagem', true))) {
            delete_post_meta($id, 'nescon_nova_imagem');
            nescon_pedido_agendar_imagem($id);

            return;
        }
        nescon_pedido_registrar($id, nescon_openai_chave() === '' ? 'Sem chave da OpenAI: o artigo usa a foto do serviço' : 'O artigo já tem imagem: mantida');
        update_post_meta($id, 'nescon_pedido_status', 'pronto');
        nescon_pedido_registrar($id, 'Pronto');
    } catch (Throwable $e) {
        update_post_meta($id, 'nescon_pedido_status', 'erro');
        update_post_meta($id, 'nescon_pedido_erro', $e->getMessage());
        nescon_pedido_registrar($id, 'Erro: ' . $e->getMessage());
    }
}

/**
 * Se o PHP for encerrado no meio (limite de tempo, falta de memória), deixa
 * isso no registro e marca o pedido como erro, em vez de ficar "escrevendo".
 */
function nescon_pedido_vigiar(int $id): void
{
    $anotar = static function (?array $falha) use ($id): void {
        $status = get_post_meta($id, 'nescon_pedido_status', true);
        if (!in_array($status, ['escrevendo', 'ilustrando'], true)) {
            return;
        }
        $motivo = $falha && str_contains((string) $falha['message'], 'Maximum execution time')
            ? 'o servidor encerrou a tarefa pelo limite de tempo do PHP'
            : 'o servidor encerrou a tarefa no meio' . ($falha ? ' (' . $falha['message'] . ')' : '');
        if ('ilustrando' === $status) {
            // O texto já está salvo: o artigo fica pronto, só sem a imagem nova.
            update_post_meta($id, 'nescon_pedido_status', 'pronto');
            update_post_meta($id, 'nescon_pedido_aviso', 'A imagem não foi gerada: ' . $motivo);
            nescon_pedido_registrar($id, 'A imagem não foi gerada: ' . $motivo);

            return;
        }
        update_post_meta($id, 'nescon_pedido_status', 'erro');
        update_post_meta($id, 'nescon_pedido_erro', $motivo);
        nescon_pedido_registrar($id, 'Erro: ' . $motivo);
    };
    // O tratamento de erro fatal do WordPress encerra o PHP antes das outras rotinas de saída.
    add_filter('wp_should_handle_php_error', static function ($tratar, $falha) use ($anotar) {
        $anotar($falha);

        return $tratar;
    }, 10, 2);
    register_shutdown_function(static fn () => $anotar(error_get_last()));
}

add_action(NESCON_PEDIDO_EVENTO, 'nescon_escrever_artigo', 10, 2);

add_action('nescon_refazer_imagem', 'nescon_ilustrar_artigo');

function nescon_ilustrar_artigo(int $id): void
{
    if ('fila' !== get_post_meta($id, 'nescon_pedido_status', true)) {
        return;
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(600);
    }
    ignore_user_abort(true);
    update_post_meta($id, 'nescon_pedido_status', 'ilustrando');
    delete_post_meta($id, 'nescon_pedido_aviso');
    nescon_pedido_registrar($id, 'cli' === PHP_SAPI ? 'Tarefa da imagem começou pelo cron do servidor' : 'Tarefa da imagem começou pelo site');
    nescon_pedido_vigiar($id);
    try {
        nescon_gerar_imagem($id);
    } catch (Throwable $e) {
        update_post_meta($id, 'nescon_pedido_aviso', 'A imagem não foi gerada: ' . $e->getMessage());
        nescon_pedido_registrar($id, 'A imagem não foi gerada: ' . $e->getMessage());
    }
    update_post_meta($id, 'nescon_pedido_status', 'pronto');
    nescon_pedido_registrar($id, 'Pronto');
}

/**
 * Cria a ilustração do artigo com a API de imagens da OpenAI, no estilo das
 * fotos do site, e a define como imagem destacada (com texto alternativo).
 */
function nescon_gerar_imagem(int $id): void
{
    $cena = (string) get_post_meta($id, 'nescon_imagem_cena', true);
    $alt = (string) get_post_meta($id, 'nescon_imagem_alt', true);
    $prompt = 'Fotografia realista, estilo editorial corporativo, luz natural suave, cores sóbrias com toques de azul-marinho e verde, '
        . 'escritório de contabilidade moderno e acolhedor no Brasil, pessoas brasileiras reais e diversas, expressões naturais, profundidade de campo rasa. '
        . 'Cena: ' . $cena . ' Sem texto, letras, números legíveis, logotipos ou marcas d\'água.';
    nescon_pedido_registrar($id, 'Pedindo a ilustração à OpenAI (cerca de 1 minuto)');
    nescon_pedido_agora($id, 'OpenAI desenhando a ilustração');
    $inicio = time();

    $resposta = wp_remote_post('https://api.openai.com/v1/images/generations', [
        'timeout' => 180,
        'headers' => ['Authorization' => 'Bearer ' . nescon_openai_chave(), 'Content-Type' => 'application/json'],
        'body' => wp_json_encode([
            'model' => 'gpt-image-1',
            'prompt' => $prompt,
            'size' => '1536x1024',
            'quality' => 'medium',
            'output_format' => 'webp',
            'n' => 1,
        ]),
    ]);
    if (is_wp_error($resposta)) {
        throw new RuntimeException('falha de conexão (' . $resposta->get_error_message() . ')');
    }
    $dados = json_decode((string) wp_remote_retrieve_body($resposta), true);
    $imagem = base64_decode((string) ($dados['data'][0]['b64_json'] ?? ''), true);
    if (200 !== wp_remote_retrieve_response_code($resposta) || !$imagem) {
        throw new RuntimeException($dados['error']['message'] ?? 'HTTP ' . wp_remote_retrieve_response_code($resposta));
    }
    nescon_pedido_registrar($id, sprintf('OpenAI entregou a imagem em %d s', time() - $inicio));

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $nome = (get_post_field('post_name', $id) ?: 'artigo-' . $id) . '.webp';
    $arquivo = wp_upload_bits($nome, null, $imagem);
    if (!empty($arquivo['error'])) {
        throw new RuntimeException($arquivo['error']);
    }
    $anexo = wp_insert_attachment([
        'post_mime_type' => 'image/webp',
        'post_title' => get_the_title($id),
        'post_status' => 'inherit',
    ], $arquivo['file'], $id);
    wp_update_attachment_metadata($anexo, wp_generate_attachment_metadata($anexo, $arquivo['file']));
    update_post_meta($anexo, '_wp_attachment_image_alt', $alt);

    $anterior = get_post_thumbnail_id($id);
    set_post_thumbnail($id, $anexo);
    if ($anterior && (int) get_post_field('post_parent', $anterior) === $id) {
        wp_delete_attachment($anterior, true);
    }
}

/* Mantém as caixinhas do "Checklist rápido" quando quem salva não é administrador. */
add_filter('wp_kses_allowed_html', static function (array $tags, $contexto): array {
    if ('post' === $contexto) {
        $tags['input'] = ['type' => true, 'disabled' => true, 'checked' => true];
    }

    return $tags;
}, 10, 2);
