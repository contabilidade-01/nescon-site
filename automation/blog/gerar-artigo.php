<?php

/**
 * Gera um rascunho de artigo para o blog da Nescon com a API do Claude.
 *
 * O artigo segue o modelo dos artigos já publicados: o script lê o artigo
 * publicado mais recente dentro da página /blog e o entrega ao Claude como
 * referência de estrutura e estilo. O resultado é criado no WordPress como
 * RASCUNHO, filho de /blog, para revisão humana antes de publicar.
 *
 * Uso: php gerar-artigo.php [--tema="Tema do artigo"] [--saida=resultado.json]
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

$opts = getopt('', ['tema::', 'saida::']);
$saida = $opts['saida'] ?? null;

$http = new Http([
    'base_uri' => rtrim(env('WP_URL'), '/').'/',
    'auth' => [env('WP_USER'), env('WP_APP_PASSWORD')],
    'headers' => ['Accept' => 'application/json'],
    'timeout' => 60,
]);

// 1. Página-mãe /blog e os artigos que já existem nela.
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
    'orderby' => 'modified',
    'order' => 'desc',
    'context' => 'edit',
]]);

$publicados = array_values(array_filter($artigos, fn ($a) => 'publish' === $a['status']));
$modelo = $publicados[0] ?? null;
if (null === $modelo || '' === trim($modelo['content']['raw'] ?? '')) {
    fwrite(STDERR, "Nenhum artigo publicado com conteúdo editável em /{$blogSlug} para servir de modelo.\n");
    exit(1);
}

$titulosExistentes = implode("\n", array_map(
    fn ($a) => '- '.$a['title']['raw'].' ('.$a['status'].')',
    $artigos,
));

// 2. Tema: argumento, próxima pauta da lista ou escolha do próprio Claude.
$pautasFile = env('PAUTAS_FILE', __DIR__.'/pautas.txt');
$tema = trim($opts['tema'] ?? '') ?: proximaPauta($pautasFile);

$hoje = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y');

$system = <<<'TXT'
Você escreve artigos para o blog da Nescon Contabilidade, escritório de contabilidade em Guarulhos (SP) que atende pequenas empresas, MEIs e profissionais liberais. O público são donos de pequenos negócios, sem formação contábil.

Regras:
- Escreva em português do Brasil, claro e direto, sem jargão desnecessário.
- Siga exatamente a estrutura, as classes CSS, os blocos e a ordem das seções do artigo-modelo. Reaproveite a mesma marcação HTML (incluindo comentários de bloco do editor, se houver) e troque apenas o conteúdo.
- Mantenha a autoria e a nota de revisão como no modelo, com a data de atualização informada.
- Valores, alíquotas, limites, prazos e regras legais devem ser conferidos em fontes oficiais (gov.br) com a busca na web. Se não conseguir confirmar um número, escreva o texto sem ele e registre o ponto em "pontos_de_revisao".
- Não invente leis, números de instruções normativas, estatísticas nem depoimentos.
- Termine com a mesma chamada para contato do modelo.
- O artigo deve ser útil por si só e responder à dúvida logo no início, como o modelo faz.
TXT;

$pedido = $tema
    ? "Tema do novo artigo: {$tema}"
    : 'Escolha você o tema do novo artigo: uma dúvida frequente de donos de pequenas empresas em Guarulhos que ainda não foi tratada nos artigos existentes.';

$user = <<<TXT
{$pedido}

Data de atualização a usar no artigo: {$hoje}

Artigos que já existem (não repita o tema):
{$titulosExistentes}

Artigo-modelo (título: {$modelo['title']['raw']}). Copie a estrutura e o estilo dele:
<artigo_modelo>
{$modelo['content']['raw']}
</artigo_modelo>
TXT;

$schema = [
    'type' => 'object',
    'properties' => [
        'titulo' => ['type' => 'string', 'description' => 'Título do artigo'],
        'slug' => ['type' => 'string', 'description' => 'Slug curto, só letras minúsculas sem acento, números e hífens'],
        'resumo' => ['type' => 'string', 'description' => 'Resumo de uma ou duas frases, usado como trecho e meta descrição'],
        'conteudo_html' => ['type' => 'string', 'description' => 'Corpo completo do artigo em HTML, na mesma marcação do modelo'],
        'pontos_de_revisao' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'Números, prazos e afirmações que o contador deve conferir antes de publicar, com a fonte usada',
        ],
    ],
    'required' => ['titulo', 'slug', 'resumo', 'conteudo_html', 'pontos_de_revisao'],
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

// 4. Rascunho no WordPress, filho de /blog e com o mesmo modelo de página.
$rascunho = wp($http, 'POST', 'pages', ['json' => [
    'title' => $artigo['titulo'],
    'slug' => $artigo['slug'],
    'excerpt' => $artigo['resumo'],
    'content' => $artigo['conteudo_html'],
    'status' => 'draft',
    'parent' => $blog['id'],
    'template' => $modelo['template'] ?? '',
]]);

$resultado = [
    'id' => $rascunho['id'],
    'titulo' => $artigo['titulo'],
    'tema' => $tema,
    'resumo' => $artigo['resumo'],
    'editar' => rtrim(env('WP_URL'), '/').'/wp-admin/post.php?post='.$rascunho['id'].'&action=edit',
    'previa' => $rascunho['link'],
    'pontos_de_revisao' => $artigo['pontos_de_revisao'],
];

$saidaJson = json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($saida) {
    file_put_contents($saida, $saidaJson);
}
echo $saidaJson, "\n";
