# Projeto: site da Nescon Contabilidade

Resumo das decisões tomadas até agora. Para instalar, rodar e publicar, veja o [README](../README.md).

## Objetivo
Site do escritório para:
1. aparecer nas primeiras posições do Google (local e nacional);
2. ser citado por assistentes de IA (ChatGPT, Claude, Perplexity, Gemini);
3. converter visitantes em clientes, com atendimento humano pelo WhatsApp;
4. ser editado pela própria equipe, sem código.

## Briefing
| Tema | Decisão |
|---|---|
| Alcance | Local/regional (atendimento presencial) + nacional (100% online) |
| Público principal | MEI, abertura de empresas e empresas do Simples Nacional |
| Conversão | Conversa de qualificação com botões → contador chama no WhatsApp |
| IA no atendimento | Removida: o site promete atendimento humano |
| Leads | E-mail para a equipe + painel com senha no site |
| Abandono | E-mail automático para a pessoa + alerta para a equipe |
| Edição | Painel visual (Decap CMS) em `/admin` |
| Tecnologia | Astro + Tailwind, hospedagem na Netlify |

## Estrutura do site
- **Home:** proposta de valor, números, serviços, "como funciona" em 3 passos, conteúdos, FAQ e chamada final. Sem a seção do contador.
- **Serviços:** abrir empresa, abrir MEI, contabilidade para Simples Nacional, trocar de contador, migrar de MEI para ME.
- **Cidades:** uma página por cidade atendida (`/contabilidade-em-<cidade>`), criada pelo painel.
- **Conteúdos (blog):** artigos no formato "resposta primeiro", com autor e CRC.
- **Institucional:** Sobre, Contato, Política de privacidade.
- **Interno:** `/painel` (leads) e `/admin` (edição de conteúdo).

## SEO e IA
- Dados estruturados: escritório de contabilidade, serviços, artigos com autor, perguntas frequentes e trilha de navegação.
- `sitemap`, `robots.txt` liberando buscadores e robôs de IA, e `/llms.txt` com o resumo do escritório.
- Cada artigo abre com uma **resposta rápida** de 2 ou 3 frases, usa subtítulos em forma de pergunta e liga para o serviço relacionado.

## Conversa de qualificação ("Fale com um contador")
Definida em `src/config/qualificacao.ts`.

1. **Contato primeiro:** nome, e-mail e WhatsApp, salvos a cada resposta.
2. **Assunto:**
   - **Quero abrir uma empresa:** faturamento anual, tipo de atividade, atividade, sócios, funcionários, prazo, cidade. Termina com a sugestão de porte (MEI, ME ou EPP).
   - **Sou MEI e estou crescendo:** faturamento no ano, motivo, atividade, prazo, cidade.
   - **Quero trocar de contador:** regime, faturamento mensal, funcionários, notas por mês, motivo, urgência.
   - **Tenho empresa e preciso de ajuda:** regime, situação, necessidade, urgência.
   - **Outro assunto:** mensagem livre.
3. **Conclusão:** aviso de que um contador vai chamar no WhatsApp, resumo por e-mail e lead classificado como quente, morno ou frio.

## Robô de abandono
- Roda a cada 10 minutos.
- Quem deixou o contato e parou de responder há mais de 30 minutos recebe **um único e-mail** com o link "continuar de onde parei".
- A equipe recebe um alerta com a pergunta em que a pessoa parou e um botão para chamar no WhatsApp.

## Painel de leads (`/painel`)
Protegido por senha. Tem lista de leads, filtros (situação, assunto, temperatura), marcação da equipe (novo, contatado, virou cliente, descartado), botão de WhatsApp e exportação CSV.

## Como publicar posts
1. Entrar em `/admin` → **Artigos do blog → Novo artigo**.
2. Título em forma de pergunta, descrição para o Google, **resposta rápida**, categoria, serviço relacionado, perguntas frequentes e texto.
3. O post passa por Rascunho → Em revisão → Publicar, com revisão do contador antes de ir ao ar.

Boas práticas: um post por pergunta real de cliente, resposta logo no começo, números e exemplos concretos, atualização quando a regra mudar e constância (1 post por semana).

## Artigos publicados
- O que é preciso para abrir uma empresa?
- MEI ou ME: qual escolher para abrir sua empresa?
- Desenquadramento do MEI: quando acontece e o que fazer
- Como trocar de contador: guia prático em 5 passos
- Quanto custa um contador para pequena empresa?

## Pendências
- [ ] Dados reais do escritório: nome, WhatsApp, CRC, endereço, horário, números, foto, domínio.
- [ ] Páginas das cidades atendidas.
- [ ] Revisão dos artigos e da política de privacidade pelo contador responsável.
- [ ] Netlify: variáveis `RESEND_API_KEY`, `EMAIL_REMETENTE`, `EMAIL_EQUIPE` e `PAINEL_SENHA`; Identity e Git Gateway para o `/admin`.
- [ ] Google Analytics 4 e perfil no Google Meu Negócio.
