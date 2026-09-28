# nescon-site

Site institucional da **Nescon Contabilidade** — WordPress + Elementor.

A estrutura da página (cabeçalho, hero, "como funciona", planos, formulário
de contato e rodapé) foi inspirada em uma página de referência do setor
contábil, adaptada com identidade visual e conteúdo próprios da Nescon.

## Duas formas de rodar este projeto

1. **Docker / EasyPanel (recomendado para VPS Hostinger)** — builda uma
   imagem própria a partir do `Dockerfile`, já com o tema Nescon, e
   provisiona o WordPress (tema, plugins, menu e a página Planos)
   automaticamente no primeiro boot. Veja
   [`docs/DEPLOY_EASYPANEL.md`](docs/DEPLOY_EASYPANEL.md).
2. **Composer + VPS tradicional (sem Docker)** — instala WordPress core e
   plugins via Composer, para quem prefere um servidor PHP/MySQL "puro".
   Veja [`docs/INSTALL.md`](docs/INSTALL.md).

## Conteúdo do repositório

- `Dockerfile`, `docker-compose.yml`, `docker/` — imagem Docker própria
  (WordPress + tema Nescon) e script de provisionamento automático, usados
  no caminho EasyPanel.
- `composer.json` — dependências (WordPress core, OceanWP, Elementor,
  Contact Form 7) para o caminho sem Docker.
- `wp-content/themes/nescon/` — tema filho do OceanWP com a identidade
  visual da Nescon (cores, tipografia, estilos das seções).
- `wp-content/elementor-templates/planos-nescon.json` — template Elementor
  importável reproduzindo a estrutura da página "Planos / Abertura de
  Empresa".
- `docs/DEPLOY_EASYPANEL.md` — deploy em VPS Hostinger com EasyPanel
  (Docker), incluindo domínio/SSL e variáveis de ambiente.
- `docs/INSTALL.md` — instalação via Composer em um servidor tradicional.

## Início rápido (local, com Docker)

```bash
cp .env.example .env   # edite com suas senhas/URLs
docker compose up --build
```

O site sobe em `http://localhost:8080` com WordPress, Elementor, tema
Nescon e a página Planos já provisionados.
