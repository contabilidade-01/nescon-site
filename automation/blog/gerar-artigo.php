<?php

/**
 * Escreve ou ajusta um artigo do blog da Nescon com a API do Claude.
 *
 * O Claude segue o guia-de-estilo.md e um artigo publicado do site como
 * exemplo. O resultado fica no WordPress como RASCUNHO, filho de /blog, para
 * o contador revisar e publicar. O tema nescon-site monta a página no visual
 * do site e inclui o artigo no /blog e no sitemap quando ele é publicado.
 *
 * Uso:
 *   php gerar-artigo.php [--tema="Tema"] [--pedido="Orientações"] [--saida=resultado.json]
 *   php gerar-artigo.php --ajustar=ID --pedido="O que mudar" [--saida=resultado.json]
 *
 * Variáveis de ambiente:
 *   ANTHROPIC_API_KEY  chave da API do Claude
 *   WP_URL             endereço do site (ex.: https://nesconcontabilidade.com.br)
 *   WP_USER            usuário do WordPress que cria o rascunho
 *   WP_APP_PASSWORD    "senha de aplicativo" desse usuário (não é a senha de login)
 *   BLOG_SLUG          slug da página-mãe dos artigos (padrão: blog)
 *   PAUTAS_FILE        lista de temas, um por linha (padrão: pautas.txt)
 */

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Anthropic\Beta\Messages\BetaJSONOutputFormat;
use Anthropic\Beta\Messages\BetaOutputConfig;
use Anthropic\Beta\Messages\BetaWebSearchTool20260209;
use Anthropic\Client as Claude;
use GuzzleHttp\Client as Http;

const MODEL = 'claude-opus-5-5';
const MAX_CONTINUATIONS = 5;
const TEMA_DIR = __DIR__.'/../../wp-content/themes/nescon-site/static/blog';
const ARTIGO_EXEMPLO = 'quanto-custa-um-contador';
const CATEGORIAS = ['Abertura de empresa', 'Regularização de MEI', 'Simples Nacional', 'Gestão', 'Impostos'];
const SERVICOS = ['abrir-empresa', 'contabilidade-simples-nacional', 'regularizacao-mei', 'trocar-de-contador'];

function env(string $name, ?string $default = null): string
{
    $value = getenv($name);
    if (false === $value || '' === $value) {
        if (null === $default) {
            fwrite(STDERR, "Defina a variável de ambiente {$name}.\n");
            exit(1);
        }

        return $default;
    }

    return $value;
}

function wp(Http $http, string $method, string $path, array $options = []): array
{
    $response = $http->request($method, 'wp-json/wp/v2/'.$path, $options);

    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

/** Primeiro tema não comentado da lista de pautas, ou null se a lista estiver vazia. */
function proximaPauta(string $file): ?string
{
    if (!is_file($file)) {
        return null;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ('' !== $line && !str_starts_with($line, '#')) {
            return $line;
        }
    }

    return null;
}

/** Corpo e perguntas frequentes de um artigo estático do site, usado como exemplo. */
function artigoEstatico(string $slug): array
{
    $html = (string) file_get_contents(TEMA_DIR.'/'.$slug.'.html');
    preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $titulo);
    preg_match('#aria-label="Resposta rápida">.*?<p class="mt-2[^>]*>(.*?)</p>#s', $html, $resumo);
    preg_match('#<div class="prose[^>]*>(.*?)</div><div class="mt-12#s', $html, $corpo);
    preg_match_all('#<h3 class="text-base">(.*?)</h3>.*?<p class="mt-3[^>]*>(.*?)</p>#s', $html, $faq, PREG_SET_ORDER);

    return [
        'titulo' => html_entity_decode(strip_tags($titulo[1] ?? '')),
        'resumo' => html_entity_decode(strip_tags($resumo[1] ?? '')),
        'corpo_html' => trim((string) preg_replace('#<h2 id="[^"]*">#', '<h2>', $corpo[1] ?? '')),
        'faq' => array_map(fn ($f) => ['pergunta' => html_entity_decode($f[1]), 'resposta' => html_entity_decode(strip_tags($f[2]))], $faq),
    ];
}

/** Texto do artigo como o WordPress guarda: corpo + perguntas em blocos "Detalhes". */
function conteudoWordPress(string $corpo, array $faq): string
{
    $html = trim($corpo);
    if ($faq) {
        $html .= "\n\n<h2>Perguntas frequentes</h2>";
        foreach ($faq as $f) {
            $html .= "\n\n<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>".htmlspecialchars($f['pergunta'])
                .'</summary><p>'.htmlspecialchars($f['resposta'])."</p></details>\n<!-- /wp:details -->";
        }
    }

    return $html;
}

/** Separa o texto guardado no WordPress em corpo e perguntas frequentes. */
function partesWordPress(string $html): array
{
    preg_match_all('#<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>#s', $html, $m, PREG_SET_ORDER);
    $faq = array_map(fn ($f) => ['pergunta' => html_entity_decode(strip_tags($f[1])), 'resposta' => html_entity_decode(trim(strip_tags($f[2])))], $m);
    $corpo = preg_replace(['#<!-- /?wp:details -->#', '#<details.*?</details>#s', '#<h2[^>]*>\s*Perguntas frequentes\s*</h2>#i'], '', $html);

    return ['corpo_html' => trim((string) $corpo), 'faq' => $faq];
}

$opts = getopt('', ['tema::', 'pedido::', 'ajustar::', 'saida::']);
$saida = $opts['saida'] ?? null;
$pedidoExtra = trim($opts['pedido'] ?? '');
$ajustarId = (int) ($opts['ajustar'] ?? 0);

$http = new Http([
    'base_uri' => rtrim(env('WP_URL'), '/').'/',
    'auth' => [env('WP_USER'), env('WP_APP_PASSWORD')],
    'headers' => ['Accept' => 'application/json'],
    'timeout' => 60,
]);

// 1. Página-mãe /blog e os artigos que já existem (no WordPress e no tema).
$blogSlug = env('BLOG_SLUG', 'blog');
$blog = wp($http, 'GET', 'pages', ['query' => ['slug' => $blogSlug, 'context' => 'edit']])[0] ?? null;
if (null === $blog) {
    fwrite(STDERR, "Página /{$blogSlug} não encontrada no WordPress.\n");
    exit(1);
}

$artigos = wp($http, 'GET', 'pages', ['query' => [
    'parent' => $blog['id'],
    'status' => 'publish,draft,pending,future',
    'per_page' => 100,
    'context' => 'edit',
]]);
// Os artigos do tema também existem como páginas vazias no WordPress; vale o título do tema.
$titulos = [];
foreach (glob(TEMA_DIR.'/*.html') as $arquivo) {
    $slug = basename($arquivo, '.html');
    $titulos[$slug] = '- '.artigoEstatico($slug)['titulo']." (/blog/{$slug}, publicado)";
}
foreach ($artigos as $a) {
    $titulos[$a['slug']] ??= '- '.$a['title']['raw'].' (/blog/'.$a['slug'].', '.$a['status'].')';
}
$titulosExistentes = implode("\n", $titulos);

$guia = (string) file_get_contents(__DIR__.'/guia-de-estilo.md');
$exemplo = json_encode(
    ['categoria' => 'Gestão', 'servico' => 'contabilidade-simples-nacional'] + artigoEstatico(ARTIGO_EXEMPLO),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
);
$hoje = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y');

$system = <<<TXT
Você escreve e ajusta artigos do blog da Nescon Contabilidade, escritório de contabilidade em Guarulhos (SP). Siga sempre o guia de estilo abaixo e use a busca na web (só sites gov.br) para confirmar números, prazos e regras.

<guia_de_estilo>
{$guia}
</guia_de_estilo>

Artigo publicado do site, como exemplo do padrão (mesmos campos que você deve devolver):
<artigo_exemplo>
{$exemplo}
</artigo_exemplo>
TXT;

// 2. O que pedir ao Claude: um artigo novo ou o ajuste de um rascunho.
$tema = null;
$atual = null;
if ($ajustarId) {
    $atual = wp($http, 'GET', "pages/{$ajustarId}", ['query' => ['context' => 'edit']]);
    if (!in_array($atual['status'], ['draft', 'pending'], true)) {
        fwrite(STDERR, "O artigo {$ajustarId} já foi publicado. Ajustes em artigos publicados são feitos no WordPress.\n");
        exit(1);
    }
    $versaoAtual = json_encode([
        'titulo' => $atual['title']['raw'],
        'slug' => $atual['slug'],
        'resumo' => $atual['excerpt']['raw'],
        'descricao' => $atual['meta']['nescon_descricao'] ?? '',
        'categoria' => $atual['meta']['nescon_categoria'] ?? '',
        'servico' => $atual['meta']['nescon_servico'] ?? '',
    ] + partesWordPress($atual['content']['raw']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $user = <<<TXT
Ajuste o rascunho abaixo conforme o pedido. Mude só o que o pedido exige e mantenha o resto como está, no padrão do guia.

Pedido de ajuste:
<pedido>
{$pedidoExtra}
</pedido>

Rascunho atual:
<rascunho>
{$versaoAtual}
</rascunho>
TXT;
} else {
    $pautasFile = env('PAUTAS_FILE', __DIR__.'/pautas.txt');
    $tema = trim($opts['tema'] ?? '') ?: proximaPauta($pautasFile);
    $pedido = $tema
        ? "Tema do novo artigo: {$tema}"
        : 'Escolha você o tema do novo artigo: uma dúvida frequente de donos de pequenas empresas que ainda não foi tratada nos artigos existentes.';
    $orientacoes = $pedidoExtra !== '' ? "\n\nOrientações de quem pediu o artigo:\n<orientacoes>\n{$pedidoExtra}\n</orientacoes>" : '';

    $user = <<<TXT
{$pedido}{$orientacoes}

Data de hoje: {$hoje}

Artigos que já existem (não repita o tema; pode linkar para eles):
{$titulosExistentes}
TXT;
}

$schema = [
    'type' => 'object',
    'properties' => [
        'titulo' => ['type' => 'string', 'description' => 'Título do artigo'],
        'slug' => ['type' => 'string', 'description' => 'Endereço curto: letras minúsculas sem acento, números e hífens'],
        'resumo' => ['type' => 'string', 'description' => 'Resposta rápida: 2 ou 3 frases que respondem ao título'],
        'descricao' => ['type' => 'string', 'description' => 'Descrição para o Google, até 170 caracteres'],
        'categoria' => ['type' => 'string', 'enum' => CATEGORIAS],
        'servico' => ['type' => 'string', 'enum' => SERVICOS, 'description' => 'Serviço chamado no fim do artigo'],
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

// 3. Geração com o Claude.
$claude = new Claude(apiKey: env('ANTHROPIC_API_KEY'));
$messages = [['role' => 'user', 'content' => $user]];

for ($i = 0; ; ++$i) {
    $message = $claude->beta->messages->create(
        model: MODEL,
        maxTokens: 16000,
        system: $system,
        messages: $messages,
        tools: [BetaWebSearchTool20260209::with(allowedDomains: ['gov.br'], maxUses: 8)],
        outputConfig: BetaOutputConfig::with(
            effort: 'high',
            format: BetaJSONOutputFormat::with(schema: $schema),
        ),
        fallbacks: 'default',
        betas: ['server-side-fallback-2026-07-01'],
    );

    // A busca na web roda no servidor; uma pausa só pede para continuar.
    if ('pause_turn' === $message->stopReason && $i < MAX_CONTINUATIONS) {
        $messages = [
            ['role' => 'user', 'content' => $user],
            ['role' => 'assistant', 'content' => $message->content],
        ];

        continue;
    }

    break;
}

if ('end_turn' !== $message->stopReason) {
    $motivo = $message->stopReason;
    if ('refusal' === $motivo && null !== $message->stopDetails) {
        $motivo .= ' ('.($message->stopDetails->category ?? 'sem categoria').')';
    }
    fwrite(STDERR, "O Claude não concluiu o artigo: {$motivo}.\n");
    exit(1);
}

$json = null;
foreach ($message->content as $block) {
    if ('text' === $block->type) {
        $json = $block->text;
    }
}
$artigo = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);

// 4. Rascunho no WordPress, filho de /blog. O tema monta o resto da página.
$dados = [
    'title' => $artigo['titulo'],
    'slug' => $artigo['slug'],
    'excerpt' => $artigo['resumo'],
    'content' => conteudoWordPress($artigo['corpo_html'], $artigo['faq']),
    'meta' => [
        'nescon_descricao' => mb_substr($artigo['descricao'], 0, 170),
        'nescon_categoria' => $artigo['categoria'],
        'nescon_servico' => $artigo['servico'],
    ],
];
$rascunho = $ajustarId
    ? wp($http, 'POST', "pages/{$ajustarId}", ['json' => $dados])
    : wp($http, 'POST', 'pages', ['json' => $dados + ['status' => 'draft', 'parent' => $blog['id']]]);

$url = rtrim(env('WP_URL'), '/');
$resultado = [
    'id' => $rascunho['id'],
    'titulo' => $artigo['titulo'],
    'tema' => $tema,
    'resumo' => $artigo['resumo'],
    'editar' => $url.'/wp-admin/post.php?post='.$rascunho['id'].'&action=edit',
    'previa' => $url.'/?page_id='.$rascunho['id'].'&preview=true',
    'endereco' => $url.'/blog/'.$rascunho['slug'],
    'pontos_de_revisao' => $artigo['pontos_de_revisao'],
];

$saidaJson = json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($saida) {
    file_put_contents($saida, $saidaJson);
}
echo $saidaJson, "\n";
