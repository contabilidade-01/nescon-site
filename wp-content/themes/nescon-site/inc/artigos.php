<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Artigos novos do blog, criados no WordPress como páginas filhas de /blog.
 *
 * O WordPress guarda só o texto (subtítulos, parágrafos, listas, tabelas e
 * as perguntas frequentes em blocos "Detalhes"), o resumo ("Resposta rápida")
 * e três campos: categoria, serviço relacionado e descrição para o Google.
 * O tema monta o resto igual aos artigos estáticos: trilha, autor, imagem,
 * índice, chamada do serviço, perguntas frequentes, dados para o Google, e
 * inclui o artigo na lista do /blog, no sitemap e no llms.txt.
 */

const NESCON_SITE_URL = 'https://nesconcontabilidade.com.br';
const NESCON_AUTOR = 'Jeandson Nascimento Santos';
const NESCON_AUTOR_CARGO = 'Contador responsável';
const NESCON_ARTIGO_MODELO = 'quanto-custa-um-contador';

/** Categorias usadas nos artigos (mesmas do site). */
function nescon_artigo_categorias(): array
{
    return ['Abertura de empresa', 'Regularização de MEI', 'Simples Nacional', 'Gestão', 'Impostos'];
}

/** Serviços que podem ser chamados no fim do artigo, com o texto e a imagem padrão. */
function nescon_artigo_servicos(): array
{
    return [
        'abrir-empresa' => [
            'titulo' => 'Abrir empresa',
            'chamada' => 'Abra sua empresa do jeito certo, desde o primeiro dia',
            'texto' => 'Cuidamos da burocracia da abertura e te ajudamos a escolher o enquadramento que faz você pagar só o imposto devido.',
            'imagem' => 'abrir-empresa-contador',
        ],
        'contabilidade-simples-nacional' => [
            'titulo' => 'Contabilidade para Simples Nacional',
            'chamada' => 'Contabilidade completa para empresas do Simples Nacional',
            'texto' => 'Impostos em dia, obrigações entregues no prazo e revisão constante para você pagar só o que é devido.',
            'imagem' => 'contabilidade-simples-nacional',
        ],
        'regularizacao-mei' => [
            'titulo' => 'Regularização de MEI',
            'chamada' => 'Coloque seu MEI em dia e evite que a pendência aumente',
            'texto' => 'Analisamos a situação completa, explicamos o que precisa ser corrigido e executamos a regularização com você.',
            'imagem' => 'regularizacao-mei',
        ],
        'trocar-de-contador' => [
            'titulo' => 'Trocar de contador',
            'chamada' => 'Troque de contador sem parar a sua empresa',
            'texto' => 'Cuidamos da transição com o escritório anterior e revisamos pendências para você começar com tudo em ordem.',
            'imagem' => 'trocar-de-contador',
        ],
    ];
}

add_action('init', static function (): void {
    add_post_type_support('page', 'excerpt');

    $auth = static fn (): bool => current_user_can('edit_pages');
    foreach (['nescon_categoria', 'nescon_servico', 'nescon_descricao'] as $meta) {
        register_post_meta('page', $meta, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => $auth,
        ]);
    }
});

/** A página é um artigo do blog (filha de /blog)? */
function nescon_eh_artigo(?WP_Post $post): bool
{
    if (!$post instanceof WP_Post || 'page' !== $post->post_type || !$post->post_parent) {
        return false;
    }

    return 'blog' === get_post_field('post_name', $post->post_parent);
}

/** Artigo que já existe como HTML estático no tema. */
function nescon_artigo_estatico(string $slug): bool
{
    return '' !== $slug && is_file(get_template_directory() . '/static/blog/' . basename($slug) . '.html');
}

/** Artigos publicados no WordPress que não têm HTML estático, do mais novo ao mais antigo. */
function nescon_artigos_dinamicos(): array
{
    $blog = get_page_by_path('blog', OBJECT, 'page');
    if (!$blog instanceof WP_Post) {
        return [];
    }

    $paginas = get_pages([
        'parent' => $blog->ID,
        'post_status' => 'publish',
        'sort_column' => 'post_date',
        'sort_order' => 'DESC',
    ]) ?: [];

    return array_values(array_filter(
        $paginas,
        static fn (WP_Post $p): bool => '' !== trim($p->post_content) && !nescon_artigo_estatico($p->post_name),
    ));
}

function nescon_artigo_url(WP_Post $post): string
{
    return NESCON_SITE_URL . '/blog/' . $post->post_name;
}

function nescon_data_extenso(string $data): string
{
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $t = strtotime($data);

    return (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
}

function nescon_artigo_descricao(WP_Post $post): string
{
    $descricao = (string) get_post_meta($post->ID, 'nescon_descricao', true);

    return $descricao !== '' ? $descricao : wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 28, '...');
}

/** Imagem do artigo: a imagem destacada ou a imagem do serviço relacionado. */
function nescon_artigo_imagem(WP_Post $post): array
{
    $id = get_post_thumbnail_id($post);
    if ($id) {
        $alt = (string) get_post_meta($id, '_wp_attachment_image_alt', true);

        return [
            'src' => (string) wp_get_attachment_image_url($id, 'large'),
            'srcset' => (string) wp_get_attachment_image_srcset($id, 'large'),
            'og' => (string) wp_get_attachment_image_url($id, 'full'),
            'alt' => $alt !== '' ? $alt : $post->post_title,
        ];
    }

    $servico = nescon_artigo_servicos()[get_post_meta($post->ID, 'nescon_servico', true)] ?? null;
    $nome = $servico['imagem'] ?? 'conteudos-contabilidade';
    // Caminhos relativos, como nos HTML estáticos: o static-render.php aponta
    // /img/ para a pasta do tema.
    $base = '/img/site/' . $nome;

    return [
        'src' => $base . '-1200.webp',
        'srcset' => "{$base}-640.webp 640w, {$base}-1200.webp 1200w, {$base}.webp 1500w",
        'og' => trailingslashit(get_template_directory_uri()) . 'static' . $base . '.webp',
        'alt' => 'Contador da Nescon atende cliente: ' . $post->post_title,
    ];
}

function nescon_ancora(string $texto): string
{
    return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower(trim($texto))), '-');
}

/**
 * Separa o texto do artigo em corpo, subtítulos (com âncoras) e perguntas
 * frequentes (blocos <details> do texto).
 */
function nescon_artigo_partes(string $html): array
{
    $faq = [];
    $html = (string) preg_replace_callback(
        '#<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>#is',
        static function (array $m) use (&$faq): string {
            $faq[] = ['pergunta' => trim(wp_strip_all_tags($m[1])), 'resposta' => trim(wp_strip_all_tags($m[2]))];

            return '';
        },
        $html,
    );
    // Remove o subtítulo "Perguntas frequentes" e os blocos vazios que sobram.
    $html = (string) preg_replace('#<h2[^>]*>\s*Perguntas frequentes\s*</h2>#i', '', $html);
    $html = (string) preg_replace('#<!-- /?wp:details[^>]*-->#', '', $html);

    $indice = [];
    $html = (string) preg_replace_callback(
        '#<h2([^>]*)>(.*?)</h2>#is',
        static function (array $m) use (&$indice): string {
            $texto = trim(wp_strip_all_tags($m[2]));
            $id = nescon_ancora($texto);
            $indice[] = ['id' => $id, 'texto' => $texto];
            $atributos = (string) preg_replace('/\s(id|class)="[^"]*"/', '', $m[1]);

            return '<h2 id="' . esc_attr($id) . '"' . $atributos . '>' . $m[2] . '</h2>';
        },
        $html,
    );

    return ['corpo' => trim($html), 'indice' => $indice, 'faq' => array_filter($faq, static fn ($f) => $f['pergunta'] !== '')];
}

/** Miolo (<main>) do artigo, com a mesma marcação dos artigos estáticos. */
function nescon_artigo_main(WP_Post $post, string $conteudo, string $cta_final): string
{
    $partes = nescon_artigo_partes($conteudo);
    $titulo = esc_html(get_the_title($post));
    $categoria = (string) get_post_meta($post->ID, 'nescon_categoria', true);
    $imagem = nescon_artigo_imagem($post);
    $data = $post->post_modified ?: $post->post_date;

    $html = '<main id="conteudo" class="flex-1">';
    $html .= '<nav aria-label="Você está em" class="container-site pt-6 text-sm text-slate-500"><ol class="flex flex-wrap items-center gap-1"><li class="flex items-center gap-1"><a href="/" class="hover:text-marinho-700">Início</a></li><li class="flex items-center gap-1"><span aria-hidden="true">/</span><a href="/blog" class="hover:text-marinho-700">Conteúdos</a></li><li class="flex items-center gap-1"><span aria-hidden="true">/</span><span aria-current="page" class="text-slate-700">' . $titulo . '</span></li></ol></nav>';
    $html .= '<article class="container-site max-w-3xl py-10">';
    if ($categoria !== '') {
        $html .= '<p class="eyebrow">' . esc_html($categoria) . '</p>';
    }
    $html .= '<h1 class="mt-3 text-4xl font-extrabold leading-tight">' . $titulo . '</h1>';
    $html .= '<p class="mt-4 text-sm text-slate-600">Por <a href="/sobre" class="font-semibold text-marinho-800 hover:underline">' . esc_html(NESCON_AUTOR) . '</a>, ' . esc_html(NESCON_AUTOR_CARGO) . ' · Atualizado em <time datetime="' . esc_attr(mysql2date('c', $data)) . '">' . esc_html(nescon_data_extenso($data)) . '</time></p>';
    $html .= sprintf(
        '<img src="%s" srcset="%s" sizes="(min-width: 768px) 768px, 100vw" alt="%s" width="1500" height="1000" fetchpriority="high" decoding="async" class="mt-8 aspect-[3/2] w-full rounded-3xl object-cover shadow-lg">',
        esc_url($imagem['src']),
        esc_attr($imagem['srcset']),
        esc_attr($imagem['alt']),
    );
    if (has_excerpt($post)) {
        $html .= '<aside class="mt-8 rounded-2xl border-l-4 border-destaque-600 bg-white p-5 shadow-sm" aria-label="Resposta rápida"><p class="text-sm font-bold uppercase tracking-wide text-destaque-700">Resposta rápida</p><p class="mt-2 text-lg leading-relaxed text-slate-800">' . esc_html($post->post_excerpt) . '</p></aside>';
    }
    if (count($partes['indice']) >= 3) {
        $html .= '<nav aria-label="Neste artigo" class="mt-8 rounded-2xl bg-marinho-50 p-5"><p class="font-semibold text-marinho-900">Neste artigo</p><ol class="mt-2 list-decimal space-y-1 pl-5 text-sm">';
        foreach ($partes['indice'] as $h) {
            $html .= '<li><a href="#' . esc_attr($h['id']) . '" class="text-marinho-700 hover:underline">' . esc_html($h['texto']) . '</a></li>';
        }
        $html .= '</ol></nav>';
    }
    $html .= '<div class="prose prose-slate prose-headings:text-marinho-900 prose-a:text-destaque-700 prose-table:text-sm mt-8 max-w-none">' . $partes['corpo'] . '</div>';

    $slug_servico = (string) get_post_meta($post->ID, 'nescon_servico', true);
    $servico = nescon_artigo_servicos()[$slug_servico] ?? null;
    if ($servico) {
        $html .= '<div class="mt-12 rounded-2xl bg-marinho-800 p-6 text-white"><p class="text-lg font-bold">' . esc_html($servico['chamada']) . '</p><p class="mt-2 text-marinho-100">' . esc_html($servico['texto']) . '</p><div class="mt-5 flex flex-col gap-3 sm:flex-row"><button type="button" data-abrir-chat class="btn-primario">Fale com um contador</button><a href="/' . esc_attr($slug_servico) . '" class="btn-claro">Conheça o serviço</a></div></div>';
    }
    $html .= '</article>';

    if ($partes['faq']) {
        $html .= '<section class="container-site mt-20" aria-labelledby="faq-titulo"><h2 id="faq-titulo" class="text-3xl font-bold">Perguntas frequentes</h2><div class="mt-8 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">';
        foreach ($partes['faq'] as $f) {
            $html .= '<details class="group p-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-marinho-900 [&amp;::-webkit-details-marker]:hidden"><h3 class="text-base">' . esc_html($f['pergunta']) . '</h3><span class="text-2xl leading-none text-destaque-600 transition-transform group-open:rotate-45" aria-hidden="true">+</span></summary><p class="mt-3 leading-relaxed text-slate-700">' . esc_html($f['resposta']) . '</p></details>';
        }
        $html .= '</div></section>';
    }

    return $html . $cta_final . '</main>';
}

/** Cabeçalho (<head>) do artigo: título, descrição, redes sociais e dados para o Google. */
function nescon_artigo_head(string $html, WP_Post $post, string $conteudo): string
{
    $titulo = get_the_title($post);
    $descricao = nescon_artigo_descricao($post);
    $url = nescon_artigo_url($post);
    $imagem = nescon_artigo_imagem($post);
    $partes = nescon_artigo_partes($conteudo);
    $json = static fn (array $dados): string => '<script type="application/ld+json">' . wp_json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

    $meta = '<title>' . esc_html($titulo) . '</title>'
        . '<meta name="description" content="' . esc_attr($descricao) . '">'
        . '<link rel="canonical" href="' . esc_url($url) . '">'
        . '<meta property="og:type" content="article"><meta property="og:locale" content="pt_BR"><meta property="og:site_name" content="Nescon Contabilidade">'
        . '<meta property="og:title" content="' . esc_attr($titulo) . '">'
        . '<meta property="og:description" content="' . esc_attr($descricao) . '">'
        . '<meta property="og:url" content="' . esc_url($url) . '">'
        . '<meta property="og:image" content="' . esc_url($imagem['og']) . '">'
        . '<meta property="og:image:alt" content="' . esc_attr($imagem['alt']) . '">'
        . '<meta name="twitter:card" content="summary_large_image">'
        . '<meta name="twitter:title" content="' . esc_attr($titulo) . '">'
        . '<meta name="twitter:description" content="' . esc_attr($descricao) . '">'
        . '<meta name="twitter:image" content="' . esc_url($imagem['og']) . '">'
        . '<meta name="twitter:image:alt" content="' . esc_attr($imagem['alt']) . '">';

    $meta .= $json(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $titulo,
        'description' => $descricao,
        'abstract' => $post->post_excerpt ?: null,
        'datePublished' => mysql2date('c', $post->post_date),
        'dateModified' => mysql2date('c', $post->post_modified ?: $post->post_date),
        'inLanguage' => 'pt-BR',
        'mainEntityOfPage' => $url,
        'author' => ['@type' => 'Person', 'name' => NESCON_AUTOR, 'jobTitle' => NESCON_AUTOR_CARGO, 'url' => NESCON_SITE_URL . '/sobre'],
        'publisher' => ['@id' => NESCON_SITE_URL . '/#escritorio'],
        'image' => $imagem['og'],
    ]));
    if ($partes['faq']) {
        $meta .= $json([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn ($f) => [
                '@type' => 'Question',
                'name' => $f['pergunta'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['resposta']],
            ], array_values($partes['faq'])),
        ]);
    }
    $meta .= $json([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => NESCON_SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Conteúdos', 'item' => NESCON_SITE_URL . '/blog'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $titulo, 'item' => $url],
        ],
    ]);

    // Tira os dados do artigo usado como molde e põe os deste artigo no lugar.
    $html = (string) preg_replace(
        [
            '#<title>.*?</title>#is',
            '#<meta\s+name="description"[^>]*>#i',
            '#<link\s+rel="canonical"[^>]*>#i',
            '#<meta\s+(property="og:|name="twitter:)[^>]*>#i',
            '#<script type="application/ld\+json">.*?</script>#is',
        ],
        '',
        $html,
    );
    $html = (string) preg_replace('#(<link rel="alternate" type="text/plain"[^>]*>)#i', '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $meta), $html, 1, $trocou);
    if (!$trocou) {
        $html = str_replace('</head>', $meta . '</head>', $html);
    }

    return $html;
}

/**
 * Página do artigo montada sobre o HTML de um artigo estático (cabeçalho,
 * rodapé, estilos e o chat "Fale com um contador" ficam iguais).
 */
function nescon_artigo_render(string $html, WP_Post $post, string $conteudo): string
{
    $cta_final = '';
    if (preg_match('#<section class="container-site mt-24">.*?</section>(?=</main>)#is', $html, $m)) {
        $cta_final = $m[0];
    }

    $main = nescon_artigo_main($post, $conteudo, $cta_final);
    $html = (string) preg_replace_callback('#<main\b[^>]*id="conteudo"[^>]*>.*?</main>#is', static fn () => $main, $html, 1);

    return nescon_artigo_head($html, $post, $conteudo);
}

/** Cartão do artigo na lista do /blog, igual aos cartões estáticos. */
function nescon_artigo_cartao(WP_Post $post): string
{
    $url = '/blog/' . $post->post_name;
    $imagem = nescon_artigo_imagem($post);
    $categoria = (string) get_post_meta($post->ID, 'nescon_categoria', true);

    return sprintf(
        '<li><article class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:border-destaque-500 hover:shadow-md"><a href="%1$s" class="block overflow-hidden"><img src="%2$s" srcset="%3$s" sizes="(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw" alt="%4$s" width="1500" height="1000" loading="lazy" decoding="async" class="aspect-[3/2] w-full object-cover transition duration-500 group-hover:scale-[1.03]"></a><div class="flex flex-1 flex-col p-6"><p class="text-xs font-semibold uppercase tracking-wide text-destaque-700">%5$s</p><h3 class="mt-3 text-xl font-bold leading-snug"><a href="%1$s" class="hover:text-destaque-700">%6$s</a></h3><p class="mt-3 flex-1 text-sm leading-relaxed text-slate-600">%7$s</p><div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4"><time datetime="%8$s" class="text-xs text-slate-500">%9$s</time><a href="%1$s" class="text-sm font-semibold text-marinho-700 group-hover:text-destaque-700">Continuar →</a></div></div></article></li>',
        esc_attr($url),
        esc_url($imagem['src']),
        esc_attr($imagem['srcset']),
        esc_attr($imagem['alt']),
        esc_html($categoria),
        esc_html(get_the_title($post)),
        esc_html(nescon_artigo_descricao($post)),
        esc_attr(mysql2date('c', $post->post_date)),
        esc_html(nescon_data_extenso($post->post_date)),
    );
}

/** Coloca os artigos novos no começo da lista "Conteúdos recentes" do /blog. */
function nescon_blog_com_artigos(string $html): string
{
    $cartoes = implode('', array_map('nescon_artigo_cartao', nescon_artigos_dinamicos()));
    if ($cartoes === '') {
        return $html;
    }

    return (string) preg_replace(
        '#(<section id="artigos".*?<ul class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">)#is',
        '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $cartoes),
        $html,
        1,
    );
}

/** Acrescenta os artigos novos ao sitemap. */
function nescon_sitemap_com_artigos(string $xml): string
{
    $urls = '';
    foreach (nescon_artigos_dinamicos() as $post) {
        $urls .= '<url><loc>' . esc_url(nescon_artigo_url($post)) . '</loc><lastmod>' . esc_html(mysql2date('Y-m-d', $post->post_modified)) . '</lastmod></url>';
    }

    return str_replace('</urlset>', $urls . '</urlset>', $xml);
}

/** Acrescenta os artigos novos à lista de guias do llms.txt. */
function nescon_llms_com_artigos(string $texto): string
{
    $linhas = '';
    foreach (nescon_artigos_dinamicos() as $post) {
        $resumo = trim(preg_replace('/\s+/', ' ', $post->post_excerpt ?: nescon_artigo_descricao($post)));
        $linhas .= '- [' . get_the_title($post) . '](' . nescon_artigo_url($post) . '): ' . $resumo . "\n";
    }
    if ($linhas === '') {
        return $texto;
    }

    $antes = (string) preg_replace('/(## Guias\n(?:- .*\n)*)/', '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $linhas), $texto, 1, $trocou);

    return $trocou ? $antes : rtrim($texto) . "\n\n## Guias\n" . $linhas;
}

/* Campos do artigo na tela de edição, para revisar e ajustar sem código. */
add_action('add_meta_boxes_page', static function (WP_Post $post): void {
    if (!nescon_eh_artigo($post)) {
        return;
    }

    add_meta_box('nescon-artigo', 'Dados do artigo', static function (WP_Post $post): void {
        wp_nonce_field('nescon_artigo', 'nescon_artigo_nonce');
        $categoria = (string) get_post_meta($post->ID, 'nescon_categoria', true);
        $servico = (string) get_post_meta($post->ID, 'nescon_servico', true);
        $descricao = (string) get_post_meta($post->ID, 'nescon_descricao', true);

        echo '<p><label for="nescon_categoria"><strong>Categoria</strong></label><br><select id="nescon_categoria" name="nescon_categoria" style="width:100%"><option value="">—</option>';
        foreach (nescon_artigo_categorias() as $c) {
            printf('<option%s>%s</option>', selected($categoria, $c, false), esc_html($c));
        }
        echo '</select></p><p><label for="nescon_servico"><strong>Serviço no fim do artigo</strong></label><br><select id="nescon_servico" name="nescon_servico" style="width:100%"><option value="">Nenhum</option>';
        foreach (nescon_artigo_servicos() as $slug => $s) {
            printf('<option value="%s"%s>%s</option>', esc_attr($slug), selected($servico, $slug, false), esc_html($s['titulo']));
        }
        printf(
            '</select></p><p><label for="nescon_descricao"><strong>Descrição para o Google</strong> (até 170 caracteres)</label><br><textarea id="nescon_descricao" name="nescon_descricao" maxlength="170" rows="4" style="width:100%%">%s</textarea></p><p class="description">O "Resumo" da página aparece como "Resposta rápida". Perguntas frequentes: use blocos "Detalhes".</p>',
            esc_textarea($descricao),
        );
    }, null, 'side', 'high');
});

add_action('save_post_page', static function (int $id): void {
    if (!isset($_POST['nescon_artigo_nonce']) || !wp_verify_nonce(sanitize_key($_POST['nescon_artigo_nonce']), 'nescon_artigo') || !current_user_can('edit_post', $id)) {
        return;
    }
    foreach (['nescon_categoria', 'nescon_servico', 'nescon_descricao'] as $campo) {
        update_post_meta($id, $campo, sanitize_text_field(wp_unslash($_POST[$campo] ?? '')));
    }
});
