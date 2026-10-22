# Artigos do blog com o Claude

Tudo acontece no wp-admin do site, no tema `nescon-site` (1.6.0 ou mais novo).

## Configuração (uma vez, administrador)

1. Crie uma chave da API em https://platform.claude.com, em **API keys**.
2. No wp-admin, em **Configurações > Claude (artigos)**, cole a chave e salve.
   (Ou defina `NESCON_CLAUDE_API_KEY` no `wp-config.php`.)
3. Para cada artigo ganhar uma ilustração própria, crie também uma chave em
   https://platform.openai.com (**API keys**) e cole no campo de imagens da
   mesma tela (ou `NESCON_OPENAI_API_KEY`). Sem ela, o artigo usa a foto do
   serviço relacionado.
4. Configure o cron do servidor. Tarefas disparadas pelo site são cortadas pela
   Hostinger em cerca de 2 minutos, e um artigo com pesquisa leva de 2 a 5. No
   hPanel, em **Avançado > Cron Jobs**, crie uma tarefa PHP a cada minuto com o
   comando mostrado em **Configurações > Claude (artigos)**
   (`php …/wp-content/themes/nescon-site/tarefas.php`). A mesma tela mostra se
   o cron está ativo. Sem ele, o tema ainda funciona pelo site, mas o Claude
   pesquisa e pensa menos para caber no tempo.
5. Em **Usuários > Adicionar novo**, crie o usuário de quem vai pedir artigos
   com a função **Redator(a)**: pede artigos, edita rascunhos e envia para
   revisão, mas não publica.

## Uso

- **Pedir artigo** (menu do wp-admin): escreva o tema e, se quiser,
  orientações. O Claude escreve no padrão de
  [`guia-de-estilo.md`](../wp-content/themes/nescon-site/guia-de-estilo.md),
  confere normas, números e prazos em sites oficiais (gov.br e leg.br) e deixa um rascunho em `/blog` em 2 a 5
  minutos. A tela se atualiza sozinha.
- **Pedir ajuste**: na mesma lista, escreva o que mudar no rascunho.
- **Gerar outra imagem**: na mesma lista, opcionalmente descrevendo a cena.
  A imagem fica como "Imagem destacada", com texto alternativo.
- **Publicar** (contador): abra **Editar**, confira a caixa
  "Conferir antes de publicar" e clique em **Publicar**. O tema monta a página
  no visual do site e inclui o artigo no `/blog`, no sitemap e no `llms.txt`.

Para mudar o padrão dos artigos, edite o `guia-de-estilo.md` do tema.

A geração roda em segundo plano pelo agendador do WordPress (WP-Cron). Em
**Acompanhar**, cada pedido mostra ao vivo o que está acontecendo, com horário:
fila, início da tarefa, cada pesquisa do Claude em sites oficiais e as páginas
encontradas, quantas palavras já foram escritas, texto salvo, imagem da OpenAI
e erros. "Última atividade há…" fica vermelho depois de 6 minutos sem
novidade. Se o servidor encerrar a tarefa no meio, o pedido vira erro com o
motivo. O texto e a imagem rodam em tarefas separadas, para cada uma caber no
limite de tempo da hospedagem. Se um pedido passar de 10 minutos parado, a tela
mostra **Tentar de novo** e, se o texto já foi salvo, **Gerar só a imagem**.
