# Site Nescon Contabilidade

Site institucional feito em [Astro](https://astro.build) para gerar contatos (leads) de empresas MEI e do Simples Nacional. Inclui:

- **SEO e IA (GEO):** schema.org, `sitemap`, `robots.txt` que libera os robôs de busca e de IA, e `/llms.txt`.
- **Qualificação em forma de conversa ("Fale com um contador")**, com botões, que salva cada resposta, classifica o lead (quente/morno/frio) e avisa a equipe por e-mail.
- **Robô de abandono:** se a pessoa deixa o contato e para de responder por 30 minutos, recebe um e-mail com o link para continuar e a equipe recebe um alerta.
- **Painel de leads** em `/painel`, protegido por senha.
- **Painel de edição** em `/admin` (Decap CMS).

## Rodar localmente

```bash
npm install
npm run dev        # http://localhost:4321
npm run build      # checagem de tipos + build em dist/
```

Painel local: rode `npm run cms` em outro terminal e acesse `http://localhost:4321/admin`.

## Onde editar

| O quê | Onde |
|---|---|
| Dados do escritório (nome, WhatsApp, CRC, endereço, horário, números, GA4) | Painel → *Dados do escritório* (`src/data/escritorio.json`) |
| Artigos do blog | Painel → *Artigos do blog* (`src/content/blog/`) |
| Páginas de serviço | Painel → *Páginas de serviço* (`src/content/servicos/`) |
| Perguntas da conversa "Fale com um contador" | `src/config/qualificacao.ts` |
| Páginas por cidade (SEO local) | Painel → *Cidades atendidas* (`src/content/cidades/`); cada cidade vira a página `/contabilidade-em-<cidade>` |

### Como escrever artigos que aparecem no Google e nas IAs
1. Use como título a pergunta que o cliente digita no Google ou no ChatGPT.
2. Preencha a **Resposta rápida** com 2 ou 3 frases que respondem a pergunta direto.
3. Use subtítulos em forma de pergunta, listas e tabelas.
4. Atualize a data sempre que revisar o conteúdo.

## Publicação (Netlify)

1. Crie o site na Netlify apontando para este repositório. O build já está configurado em `netlify.toml`.
2. Em *Site configuration → Environment variables*, crie as variáveis do `.env.example`:
   - `RESEND_API_KEY`, `EMAIL_REMETENTE` e `EMAIL_EQUIPE`: crie uma conta grátis no [Resend](https://resend.com) e verifique o domínio do escritório.
   - `PAINEL_SENHA`: a senha do painel de leads.
3. Para o painel de edição (`/admin`): ative *Identity* e *Git Gateway* e convide a equipe por e-mail.
4. Os leads ficam guardados no Netlify Blobs e aparecem em `/painel`. O robô de abandono roda sozinho a cada 10 minutos (função agendada `abandono`).

## Pendências antes de publicar
- [ ] Trocar os dados provisórios em `escritorio.json`: WhatsApp, CRC, endereço, números reais, foto e domínio.
- [ ] Criar as páginas das cidades reais no painel (o modelo `exemplo` está como rascunho).
- [ ] Revisar os 5 artigos iniciais e a política de privacidade com o responsável técnico.
- [ ] Configurar o GA4 e o perfil no Google Meu Negócio.
