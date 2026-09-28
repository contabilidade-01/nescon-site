# nescon-site

Site institucional da **Nescon Contabilidade** — WordPress + Elementor.

A estrutura da página (cabeçalho, hero, "como funciona", planos, formulário
de contato e rodapé) foi inspirada em uma página de referência do setor
contábil, adaptada com identidade visual e conteúdo próprios da Nescon.

## Conteúdo do repositório

- `composer.json` — dependências (WordPress core, OceanWP, Elementor,
  Contact Form 7), instaladas via `composer install`.
- `wp-content/themes/nescon/` — tema filho do OceanWP com a identidade
  visual da Nescon (cores, tipografia, estilos das seções).
- `wp-content/elementor-templates/planos-nescon.json` — template Elementor
  importável reproduzindo a estrutura da página "Planos / Abertura de
  Empresa".
- `docs/INSTALL.md` — passo a passo de instalação, configuração e deploy.

## Início rápido

```bash
composer install
```

Depois siga [`docs/INSTALL.md`](docs/INSTALL.md) para ativar o tema, os
plugins e importar o template da página.
