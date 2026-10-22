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

1. **Título**: começa pela palavra-chave principal e diz para quem é ou o que
   o leitor vai saber ("Fim da escala 6x1: o que muda para pequenas empresas?",
   "Como trocar de contador: guia prático em 5 passos"). Até 60 caracteres.
2. **Resposta rápida** (campo "resumo"): 2 ou 3 frases que respondem à
   pergunta do título por completo. É o trecho que o Google e as IAs citam.
3. **Corpo**, de 600 a 1.200 palavras:
   - 3 a 6 subtítulos `<h2>` em forma de pergunta, do jeito que o empresário
     pesquisa no Google. A ordem segue a intenção de busca: primeiro a dúvida
     mais urgente. Em mudança de lei ou de regra, a ordem é: quando começa a
     valer → o que muda → quem é mais afetado → quanto custa → impostos e
     Simples Nacional → como se preparar.
   - Dúvidas paralelas que muita gente pesquisa entram como `<h3>` dentro da
     seção ligada ("A escala 12x36 vai acabar?"), com resposta curta e honesta
     quando o caso pede análise própria.
   - Quando houver prazos ou etapas, uma tabela "Período | Regra" com datas
     concretas.
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
- **Descrição para o Google**: até 155 caracteres, com a palavra-chave e uma
  informação concreta (data, número, prazo): "Fim da escala 6x1: veja quando
  começa a valer, como ficam as 42 e 40 horas semanais e os impactos nos
  custos das pequenas empresas." Diferente da resposta rápida.
- **Endereço (slug)**: curto (3 a 6 palavras), com a palavra-chave, sem
  acentos nem palavras vazias como "de", "para", "o"
  ("fim-escala-6x1-pequenas-empresas").
- **Chamada do fim do artigo**: um título curto em forma de pergunta ligada ao
  problema do leitor ("Sua empresa está preparada para a nova jornada de
  trabalho?") e 1 ou 2 frases sobre como a Nescon ajuda nesse caso ("A redução
  da jornada pode exigir ajustes nas escalas, na folha e nos custos. A Nescon
  ajuda sua empresa a avaliar esses impactos e planejar a adaptação."). Nada
  de chamada genérica como "contabilidade completa".

## SEO

- Escolha uma **palavra-chave principal** (o termo que o empresário digita,
  ex.: "fim da escala 6x1") e de 3 a 6 **secundárias e de cauda longa**
  ("quando começa a valer o fim da escala 6x1", "fim da escala 6x1 no Simples
  Nacional", "custo do funcionário com jornada de 40 horas").
- A principal aparece no título, na resposta rápida, na descrição, no endereço
  e em pelo menos um `<h2>`. As outras aparecem nos `<h2>`, `<h3>` e nas
  perguntas frequentes, onde fizerem sentido. Sempre de forma natural, sem
  repetir à força.

## Imagem

Cada artigo tem uma ilustração própria, no estilo das fotos do site: fotografia
realista de escritório, com pessoas brasileiras diversas numa cena ligada ao
tema (ex.: contadora e empresário avaliando numa balança o custo do serviço).

- **Cena da imagem**: 1 ou 2 frases com quem aparece (gênero, cor da pele,
  papel: contador(a), empresário(a), cliente), o que estão fazendo e um objeto
  que remeta ao tema (documentos, gráfico, calculadora, maquininha, notebook).
  Sem texto, telas legíveis ou marcas.
- **Texto alternativo**: curto e objetivo, com a ação e o assunto do artigo,
  sem descrever a aparência das pessoas e sem inventar o que não está na cena
  ("Empresários analisando escala de trabalho e custos de funcionários após o
  fim da escala 6x1").

## Exatidão

- Valores, alíquotas, limites, prazos e regras legais só entram se
  confirmados em fonte oficial na busca: sites gov.br (Planalto, Receita,
  ministérios) e leg.br (Senado e Câmara). Cite o órgão no texto quando ajudar
  ("segundo a Receita Federal").
- **Mudança de lei**: confirme a situação na data de hoje: projeto em
  votação, aprovada, sancionada ou promulgada, publicada, com número e data
  da norma ("Emenda Constitucional nº 141/2026, publicada em 16/10/2026"). Não
  descreva como pendente uma etapa que já aconteceu.
- **Datas de vigência** saem do texto oficial da norma. Se a contagem do prazo
  deixar dúvida ("decorridos 2 meses da publicação"), escreva a regra como está
  na norma, informe a data divulgada pelo órgão oficial, se houver, e anote a
  contagem nos pontos de revisão.
- **Sem afirmações absolutas** quando a regra tem exceções. Em vez de "obriga
  todas as empresas", escreva "a nova regra prevê..., com exceções previstas na
  legislação" e cite as exceções que importam para pequenas empresas: acordo
  ou convenção coletiva, categorias com regra própria e tratamento especial
  para MEI, microempresa e empresa de pequeno porte.
- **Termos tributários com precisão**:
  - Fator R: relação entre a folha de salários dos últimos 12 meses (salários,
    pró-labore e encargos) e a receita do mesmo período. Não é o custo da folha
    de um mês.
  - Contribuição previdenciária patronal (CPP): pode estar dentro do DAS ou
    ser paga à parte, conforme a atividade e o anexo do Simples.
  - Quando a mudança não for tributária, diga que ela "não altera diretamente
    as alíquotas ou os anexos do Simples Nacional", sem afirmar que nada muda.
- **Exemplos de cálculo** trazem a ressalva do que consideram ("no exemplo,
  sem considerar outras mudanças na folha") e de que situações específicas
  precisam de análise.

## Fontes

- Em temas de lei, prazo ou obrigação, inclua links para as fontes primárias
  oficiais no corpo: o texto da norma no Planalto (planalto.gov.br) e o
  comunicado oficial do Senado, da Câmara ou do órgão responsável. Use o
  endereço completo (https://...), só de páginas que você abriu na busca.
- Termine a seção sobre a regra com uma linha discreta:
  `<p><em>Fonte: <a href="https://...">Emenda Constitucional nº 141/2026 (Planalto)</a> e <a href="https://...">Agência Senado</a>.</em></p>`
- Sem número confirmado, escreva o texto sem ele e anote o ponto para revisão.
- Nunca invente leis, números de normas, estatísticas, casos de clientes ou
  depoimentos.
- Não prometa resultado tributário. Cada empresa depende de atividade,
  faturamento e situação fiscal.
