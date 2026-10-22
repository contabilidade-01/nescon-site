# Artigos do blog com o Claude

Às segundas e quintas, o GitHub Actions ([`artigo-blog.yml`](../../.github/workflows/artigo-blog.yml))
gera um artigo novo para o blog e o cria no WordPress como **rascunho**,
dentro da página `/blog`. Em seguida, abre uma issue neste repositório com o
link para editar e a lista de pontos que o contador deve conferir antes de
publicar. O GitHub avisa por e-mail quando a issue é aberta.

O artigo segue o modelo dos que já existem: o script entrega ao Claude o
artigo publicado mais recente de `/blog` como referência de estrutura,
classes e estilo. Números e prazos são pesquisados só em sites `gov.br`.

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

## Uso

- **Temas:** edite [`pautas.txt`](pautas.txt), um tema por linha. A automação
  usa o primeiro e o remove da lista. Com a lista vazia, o Claude escolhe um
  tema que ainda não foi tratado.
- **Gerar agora:** em **Actions > Artigo do blog > Run workflow**, com um tema
  opcional.
- **Publicar:** revise o rascunho, confira os pontos da issue, publique,
  adicione o card do artigo na página `/blog` e feche a issue.

## Rodar localmente

```bash
cd automation/blog
composer install
ANTHROPIC_API_KEY=... WP_URL=... WP_USER=... WP_APP_PASSWORD=... \
  php gerar-artigo.php --tema="Tema do artigo"
```

Cada artigo custa menos de US$ 1 na API (modelo `claude-opus-5-5`, com busca na web). A busca na web precisa estar liberada nas configurações da sua organização no Claude Platform.
