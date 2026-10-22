# Guia de estilo dos artigos da Nescon

Este é o padrão que o Claude segue para escrever e ajustar artigos do blog.
Mude este arquivo para mudar o padrão: a próxima geração já usa a versão nova.

## Para quem escrevemos

Donos de pequenas empresas, MEIs e profissionais liberais, sem formação
contábil, em Guarulhos e no resto do Brasil. Eles chegam pelo Google ou por
assistentes de IA com uma dúvida concreta e querem saber o que fazer.

## Tom

- Português do Brasil, claro e direto, frases curtas.
- Fala com o leitor por "você". Sem gírias, sem exclamações, sem promessas
  ("economize até 50%", "o melhor contador").
- Explica o termo técnico na primeira vez que aparece.
- Sem alarmismo. Quando o caso depende de análise, diz isso com naturalidade
  e indica falar com um contador.

## Estrutura do artigo

O tema monta automaticamente a trilha, o título, o autor, a imagem, o índice,
a chamada do serviço e a seção de perguntas frequentes. O texto do artigo traz
só o miolo:

1. **Título**: a pergunta que o cliente pesquisa, ou o assunto + benefício
   ("Como trocar de contador: guia prático em 5 passos"). Até 65 caracteres.
2. **Resposta rápida** (campo "resumo"): 2 ou 3 frases que respondem à
   pergunta do título por completo. É o trecho que o Google e as IAs citam.
3. **Corpo**, de 600 a 1.200 palavras:
   - 3 a 6 subtítulos `<h2>`, de preferência em forma de pergunta. O primeiro
     responde ao título; os seguintes aprofundam (o que muda, quanto custa,
     o que fazer agora).
   - Parágrafos curtos (até 4 linhas), listas `<ul>`/`<ol>` sempre que houver
     itens ou passos, com o termo-chave em `<strong>` no início do item.
   - Uma tabela `<table>` com `<thead>` quando houver comparação ou
     "o que definir / exemplo".
   - Quando fizer sentido, um "Checklist rápido" no fim:
     `<ul class="contains-task-list"><li class="task-list-item"><input type="checkbox" disabled> Item</li>...</ul>`
   - Uma frase de fechamento com o próximo passo.
   - Por último, sempre esta nota de revisão, igual:
     `<p>Este conteúdo foi revisado pela equipe da <a href="/contabilidade-em-guarulhos">Nescon Contabilidade em Guarulhos</a>. Atendemos empresas da região presencialmente, mediante agendamento, e negócios de todo o Brasil de forma online.</p>`
4. **Perguntas frequentes**: 2 a 4 perguntas que o corpo ainda não respondeu,
   cada resposta com 1 a 3 frases.

## HTML permitido no corpo

Só `h2`, `h3`, `p`, `ul`, `ol`, `li`, `strong`, `em`, `a`, `table`, `thead`,
`tbody`, `tr`, `th`, `td`, `blockquote` e o checklist acima. Sem `h1`, sem
classes ou estilos próprios, sem imagens e sem scripts: o visual vem do tema.

Links internos são bem-vindos quando ajudam o leitor, com caminho relativo:
`/abrir-empresa`, `/contabilidade-simples-nacional`, `/regularizacao-mei`,
`/trocar-de-contador`, `/contabilidade-em-guarulhos`, `/contato` e os outros
artigos em `/blog/endereco-do-artigo`.

## Campos do artigo

- **Categoria**: Abertura de empresa, Regularização de MEI, Simples Nacional,
  Gestão ou Impostos.
- **Serviço no fim do artigo**: o serviço mais ligado ao tema
  (abrir-empresa, contabilidade-simples-nacional, regularizacao-mei ou
  trocar-de-contador).
- **Descrição para o Google**: até 170 caracteres, diz o que o leitor vai
  aprender. Diferente da resposta rápida.
- **Endereço (slug)**: curto, sem acentos, só letras minúsculas, números e
  hífens ("limite-faturamento-mei").

## Imagem

Cada artigo tem uma ilustração própria, no estilo das fotos do site: fotografia
realista de escritório, com pessoas brasileiras diversas numa cena ligada ao
tema (ex.: contadora e empresário avaliando numa balança o custo do serviço).

- **Cena da imagem**: 1 ou 2 frases com quem aparece (gênero, cor da pele,
  papel: contador(a), empresário(a), cliente), o que estão fazendo e um objeto
  que remeta ao tema (documentos, gráfico, calculadora, maquininha, notebook).
  Sem texto, telas legíveis ou marcas.
- **Texto alternativo**: descreve objetivamente a cena, no formato dos
  artigos atuais ("Empresário branco e contadora negra avaliam em uma balança o
  custo e os serviços de contabilidade").

## Exatidão

- Valores, alíquotas, limites, prazos e regras legais só entram se
  confirmados em fonte oficial (sites gov.br) na busca. Cite o órgão no texto
  quando ajudar ("segundo a Receita Federal").
- Sem número confirmado, escreva o texto sem ele e anote o ponto para revisão.
- Nunca invente leis, números de normas, estatísticas, casos de clientes ou
  depoimentos.
- Não prometa resultado tributário. Cada empresa depende de atividade,
  faturamento e situação fiscal.
