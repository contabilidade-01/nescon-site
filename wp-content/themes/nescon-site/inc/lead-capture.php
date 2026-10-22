<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Guarda uma cópia restrita de cada atendimento no próprio WordPress. */
add_action('init', static function (): void {
    register_post_type('nescon_lead', [
        'labels' => [
            'name' => 'Atendimentos do site',
            'singular_name' => 'Atendimento do site',
            'menu_name' => 'Leads Nescon',
            'all_items' => 'Todos os atendimentos',
            'view_item' => 'Ver atendimento',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => false,
        'exclude_from_search' => true,
        'supports' => ['title', 'editor'],
        'menu_icon' => 'dashicons-email-alt',
    ]);
});

function nescon_lead_limpar_linhas($valor): array
{
    if (!is_array($valor)) {
        return [];
    }

    $linhas = [];
    foreach (array_slice($valor, 0, 30) as $linha) {
        if (!is_array($linha)) {
            continue;
        }
        $rotulo = sanitize_text_field((string) ($linha['rotulo'] ?? ''));
        $conteudo = sanitize_textarea_field((string) ($linha['valor'] ?? ''));
        if ($rotulo !== '' && $conteudo !== '') {
            $linhas[] = [
                'rotulo' => mb_substr($rotulo, 0, 80),
                'valor' => mb_substr($conteudo, 0, 600),
            ];
        }
    }
    return $linhas;
}

function nescon_lead_valor(array $linhas, string $rotulo, string $padrao = ''): string
{
    foreach ($linhas as $linha) {
        if (strcasecmp($linha['rotulo'], $rotulo) === 0) {
            return $linha['valor'];
        }
    }
    return $padrao;
}

function nescon_lead_email_html(string $nome, string $assunto, string $pagina, string $recomendacao, array $linhas, string $whatsapp): string
{
    $tabela = '';
    foreach ($linhas as $linha) {
        $tabela .= '<tr><td style="padding:7px 10px 7px 0;color:#64748b;vertical-align:top;white-space:nowrap">' . esc_html($linha['rotulo']) . '</td>';
        $tabela .= '<td style="padding:7px 0;color:#1e293b">' . nl2br(esc_html($linha['valor'])) . '</td></tr>';
    }

    $bloco_recomendacao = $recomendacao === '' ? '' : '<p style="background:#f2f5fa;border-radius:10px;padding:12px 14px">' . esc_html($recomendacao) . '</p>';
    $link_whatsapp = 'https://wa.me/' . rawurlencode($whatsapp) . '?text=' . rawurlencode('Olá, ' . $nome . '! Aqui é da Nescon Contabilidade. Recebemos seu contato pelo site.');

    return '<!doctype html><html lang="pt-BR"><body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1e293b">'
        . '<div style="max-width:620px;margin:0 auto;padding:24px">'
        . '<p style="font-size:18px;font-weight:700;color:#10284e">Nescon Contabilidade</p>'
        . '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;line-height:1.5">'
        . '<h1 style="font-size:21px;margin:0 0 8px;color:#10284e">Novo atendimento pelo site</h1>'
        . '<p style="margin-top:0"><strong>' . esc_html($nome) . '</strong> concluiu o questionário sobre <strong>' . esc_html($assunto) . '</strong>.</p>'
        . $bloco_recomendacao
        . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $tabela . '</table>'
        . '<p style="margin:24px 0 12px"><a href="' . esc_url($link_whatsapp) . '" style="display:inline-block;background:#15803d;color:#fff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:999px">Chamar no WhatsApp</a></p>'
        . '<p style="font-size:12px;color:#64748b">Página de origem: ' . esc_html($pagina ?: '/') . '<br>Recebido em ' . esc_html(wp_date('d/m/Y \à\s H:i')) . '</p>'
        . '</div></div></body></html>';
}

function nescon_receber_lead(WP_REST_Request $request): WP_REST_Response
{
    if ((string) $request->get_param('site') !== '') {
        return new WP_REST_Response(['status' => 'em_andamento'], 200);
    }

    if ($request->get_param('concluido') !== true) {
        return new WP_REST_Response(['status' => 'em_andamento'], 200);
    }

    $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));
    $limite_chave = 'nescon_lead_' . md5($ip);
    $tentativas = (int) get_transient($limite_chave);
    if ($tentativas >= 10) {
        return new WP_REST_Response(['erro' => 'Muitas tentativas. Aguarde alguns minutos.'], 429);
    }
    set_transient($limite_chave, $tentativas + 1, HOUR_IN_SECONDS);

    $respostas = $request->get_param('respostas');
    $respostas = is_array($respostas) ? $respostas : [];
    $nome = mb_substr(sanitize_text_field((string) ($respostas['nome'] ?? '')), 0, 80);
    $whatsapp = preg_replace('/\D+/', '', (string) ($respostas['whatsapp'] ?? '')) ?: '';
    $email = sanitize_email((string) ($respostas['email'] ?? ''));
    $trilha = sanitize_key((string) ($respostas['trilha'] ?? ''));

    if (mb_strlen($nome) < 2 || !preg_match('/^55\d{10,11}$/', $whatsapp) || $trilha === '') {
        return new WP_REST_Response(['erro' => 'Dados de contato incompletos.'], 400);
    }
    if (($respostas['email'] ?? '') !== '' && !is_email($email)) {
        return new WP_REST_Response(['erro' => 'E-mail inválido.'], 400);
    }

    $id = sanitize_text_field((string) $request->get_param('id'));
    if (!preg_match('/^[a-f0-9-]{20,50}$/i', $id)) {
        $id = wp_generate_uuid4();
    }

    $existente = get_posts([
        'post_type' => 'nescon_lead',
        'post_status' => 'private',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_nescon_lead_id',
        'meta_value' => $id,
    ]);
    if ($existente) {
        return new WP_REST_Response(['id' => $id, 'token' => '', 'status' => 'completo'], 200);
    }

    $linhas = nescon_lead_limpar_linhas($request->get_param('resumo'));
    $assunto = nescon_lead_valor($linhas, 'Assunto', $trilha);
    $pagina = mb_substr(sanitize_text_field((string) $request->get_param('pagina')), 0, 200);
    $recomendacao = mb_substr(sanitize_textarea_field((string) $request->get_param('recomendacao')), 0, 1000);
    $conteudo = implode("\n", array_map(static fn(array $linha): string => $linha['rotulo'] . ': ' . $linha['valor'], $linhas));

    $post_id = wp_insert_post([
        'post_type' => 'nescon_lead',
        'post_status' => 'private',
        'post_title' => $nome . ' — ' . $assunto,
        'post_content' => $conteudo,
    ], true);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['erro' => 'Não foi possível registrar o atendimento.'], 500);
    }

    update_post_meta($post_id, '_nescon_lead_id', $id);
    update_post_meta($post_id, '_nescon_whatsapp', $whatsapp);
    update_post_meta($post_id, '_nescon_email', $email);
    update_post_meta($post_id, '_nescon_pagina', $pagina);

    $destinatario = (string) apply_filters('nescon_lead_email_equipe', 'contato@nesconcontabilidade.com.br');
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Nescon Contabilidade <contato@nesconcontabilidade.com.br>',
    ];
    if ($email !== '') {
        $headers[] = 'Reply-To: ' . $nome . ' <' . $email . '>';
    }

    $enviado = wp_mail(
        $destinatario,
        'Novo atendimento pelo site: ' . $nome . ' — ' . $assunto,
        nescon_lead_email_html($nome, $assunto, $pagina, $recomendacao, $linhas, $whatsapp),
        $headers
    );
    update_post_meta($post_id, '_nescon_email_enviado', $enviado ? 'sim' : 'nao');

    return new WP_REST_Response([
        'id' => $id,
        'token' => '',
        'status' => 'completo',
        'emailEnviado' => $enviado,
    ], 201);
}

add_action('rest_api_init', static function (): void {
    register_rest_route('nescon/v1', '/lead', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'nescon_receber_lead',
        'permission_callback' => '__return_true',
    ]);
});

