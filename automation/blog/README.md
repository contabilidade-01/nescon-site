# Artigos do blog com o Claude

O Claude escreve os artigos no padrão da Nescon e os deixa como **rascunho**
no WordPress, dentro de `/blog`. O contador revisa e clica em **Publicar**.
Ninguém precisa montar layout: o tema `nescon-site` monta a página no visual
do site e, depois de publicado, coloca o artigo na lista do `/blog`, no
sitemap e no `llms.txt`.

O padrão está em [`guia-de-estilo.md`](guia-de-estilo.md) (tom, estrutura,
campos, regras de exatidão). Mude o guia para mudar o padrão. Números e prazos
são pesquisados só em sites `gov.br`.

## Como pedir

- **Artigo novo:** em **Issues > New issue > Pedir um artigo novo**, escreva o
  tema e, se quiser, orientações. Em alguns minutos o Claude responde na
  própria issue com os links do rascunho e os pontos a conferir.
- **Ajuste:** comente na mesma issue começando com `Ajuste:`, por exemplo
  `Ajuste: troque o exemplo por um salão de beleza`. O Claude reescreve o
  mesmo rascunho e responde de novo. Só vale para rascunhos; artigo já
  publicado se ajusta no WordPress.
- **Automático:** às segundas e quintas sai um artigo com o próximo tema de
  [`pautas.txt`](pautas.txt), com uma issue de revisão. Com a lista vazia, o
  Claude escolhe um tema que ainda não foi tratado.
- **Publicar:** abra o link "Editar ou publicar", confira os pontos da issue,
  clique em **Publicar** e feche a issue.

Só quem tem acesso a este repositório no GitHub consegue pedir artigos e
ajustes (adicione a pessoa em **Settings > Collaborators**).

## Configuração (uma vez)

1. **Senha de aplicativo no WordPress.** Entre no wp-admin, abra
   **Usuários > Perfil**, vá até **Senhas de aplicativo**, dê o nome
   `GitHub` e clique em **Adicionar**. Copie a senha gerada. Ela só dá acesso
   à API, não ao login, e pode ser revogada a qualquer momento.
2. **Chave da API do Claude.** Crie em https://platform.claude.com, em
   **API keys**.
3. **No GitHub**, em **Settings > Secrets and variables > Actions** deste
   repositório:
   - aba **Secrets**: `ANTHROPIC_API_KEY` (a chave do passo 2) e
     `WP_APP_PASSWORD` (a senha do passo 1);
   - aba **Variables**: `WP_URL` (ex.: `https://nesconcontabilidade.com.br`)
     e `WP_USER` (o usuário do passo 1).
4. O site precisa estar com o tema `nescon-site` 1.5.0 ou mais novo
   (`wp-content/themes/nescon-site`), que guarda os campos do artigo.

## Rodar localmente

```bash
cd automation/blog
composer install
ANTHROPIC_API_KEY=... WP_URL=... WP_USER=... WP_APP_PASSWORD=... \
  php gerar-artigo.php --tema="Tema do artigo" --pedido="Orientações"
# ajustar um rascunho:
php gerar-artigo.php --ajustar=123 --pedido="O que mudar"
```
