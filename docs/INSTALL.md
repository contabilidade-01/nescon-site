# Instalação — Site Nescon (WordPress + Elementor)

Este repositório contém apenas o **código próprio** do site da Nescon: o tema
filho `nescon` e um template Elementor exportado. O WordPress core e os
plugins de terceiros (Elementor, OceanWP, Contact Form 7) **não são
versionados aqui** — eles são instalados via Composer, seguindo a prática
padrão para projetos WordPress.

## 1. Pré-requisitos

- PHP >= 8.0 e Composer
- Um banco de dados MySQL/MariaDB
- Hospedagem WordPress (ou ambiente local: Local, DevKinsta, wp-env, etc.)
- **Elementor Pro** (licença paga, comprada separadamente) — necessário para
  os widgets `Nav Menu`, `Form` e `Price Table` usados no template. Sem o
  Elementor Pro, use os fallbacks descritos na seção 5.

## 2. Instalar WordPress + dependências

```bash
composer install
```

Isso baixa o WordPress core para `wp/`, o tema `oceanwp` e os plugins
`elementor` e `contact-form-7` para dentro de `wp-content/`.

Depois, faça o upload manual do **Elementor Pro** (`.zip` obtido na sua
conta Elementor) em `wp-content/plugins/elementor-pro/` e ative-o pelo
admin do WordPress.

## 3. Ativar tema e plugins

No admin do WordPress:

1. **Aparência > Temas** → ative **OceanWP**.
2. Copie/link a pasta `wp-content/themes/nescon` deste repositório para o
   `wp-content/themes/` da instalação e ative o tema **Nescon
   Contabilidade** (tema filho do OceanWP).
3. **Plugins** → ative **Elementor**, **Elementor Pro** e **Contact Form 7**.

## 4. Configurar cores globais no Elementor

Em **Elementor > Configurações do Site > Cores Globais**, cadastre:

| Nome          | Cor       |
|---------------|-----------|
| Navy (Nescon) | `#0A2540` |
| Gold (Nescon) | `#C89B3C` |
| Texto         | `#4A4A4A` |
| Fundo claro   | `#F7F8FA` |

## 5. Importar o template da página "Planos"

1. Crie o menu principal em **Aparência > Menus**, atribua-o à posição
   **Menu Principal** (slug `main-menu`) — é o menu usado pelo widget
   `Nav Menu` do cabeçalho.
2. Crie uma nova página (ex.: "Planos").
3. Edite com Elementor → **Templates > Importar Templates** → envie
   `wp-content/elementor-templates/planos-nescon.json`.
4. Insira o template importado na página e publique.

### Sem Elementor Pro?

O template usa três widgets Pro (`nav-menu`, `form`, `price-table`) para
reproduzir fielmente a estrutura da página de referência. Se você não tiver
o Elementor Pro, substitua manualmente após a importação:

- `Nav Menu` → widget gratuito **Menu Âncora** ou um menu de texto simples.
- `Form` → shortcode do **Contact Form 7** dentro de um widget "Shortcode".
- `Price Table` → três colunas com widgets gratuitos **Ícone + Lista de
  Ícones + Botão**.

## 6. Rastreamento e integrações (Google Analytics, GTM, Meta Pixel, chat)

O site de referência usado como inspiração de estrutura tinha IDs de
Google Tag Manager, Google Analytics, Google Ads e Meta Pixel **da conta do
concorrente**, além de um widget de chat de terceiros (Leadster/Neurolead)
vinculado à conta deles. Esses IDs e scripts **não foram copiados** para
este projeto — reutilizá-los enviaria os dados de visitantes do site da
Nescon para as contas de analytics/ads de outra empresa.

Ao publicar o site, adicione suas próprias integrações:

- Google Tag Manager: **Ocean Extra > Configurações do Tema** ou um plugin
  dedicado (ex.: "GTM4WP"), com o *container ID* da Nescon.
- Meta Pixel / Google Ads: plugins próprios ou snippets no `functions.php`,
  com os IDs da conta da Nescon.
- Chat/WhatsApp: qualquer widget de chat da preferência da Nescon (ex.:
  link direto para WhatsApp, já incluso no rodapé do template).

## 7. Deploy em nuvem

Para publicar em um provedor de hospedagem WordPress (ex.: um servidor com
PHP + MySQL), faça o deploy de:

- O output de `composer install` (`wp/`, `wp-content/plugins`,
  `wp-content/themes/oceanwp`)
- O conteúdo deste repositório (`wp-content/themes/nescon`)
- Um `wp-config.php` próprio do ambiente (não versionado — contém segredos)

Se preferir um host gerenciado de WordPress (WP Engine, Kinsta, Cloudways,
etc.), basta enviar os mesmos arquivos via SFTP/Git deploy conforme a
documentação do provedor escolhido.
