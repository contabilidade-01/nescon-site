# Site Nescon Contabilidade

Site institucional feito em [Astro](https://astro.build) para gerar contatos (leads) de empresas MEI e do Simples Nacional. Inclui:

- **SEO e IA (GEO):** schema.org, `sitemap`, `robots.txt` que libera os robôs de busca e de IA, e `/llms.txt`.
- **Chat com IA que passa o atendimento para a equipe no WhatsApp.**
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
| Páginas por cidade (SEO local) | Painel → *Cidades atendidas* (`src/content/cidades/`); cada cidade vira a página `/contabilidade-em-<cidade>` |

### Como escrever artigos que aparecem no Google e nas IAs
1. Use como título a pergunta que o cliente digita no Google ou no ChatGPT.
2. Preencha a **Resposta rápida** com 2 ou 3 frases que respondem a pergunta direto.
3. Use subtítulos em forma de pergunta, listas e tabelas.
4. Atualize a data sempre que revisar o conteúdo.

## Publicação (Netlify)

1. Crie o site na Netlify apontando para este repositório. O build já está configurado em `netlify.toml`.
2. Em *Site configuration → Environment variables*, crie `ANTHROPIC_API_KEY`. Se quiser outro modelo no chat, crie também `CHAT_MODEL` (o padrão é `claude-opus-5`; `claude-haiku-4-5` sai mais barato).
3. Para o painel: ative *Identity* e *Git Gateway* e convide a equipe por e-mail.
4. Os leads do chat ficam em *Forms → lead-chat*.

## Pendências antes de publicar
- [ ] Trocar os dados provisórios em `escritorio.json`: WhatsApp, CRC, endereço, números reais, foto e domínio.
- [ ] Criar as páginas das cidades reais no painel (o modelo `exemplo` está como rascunho).
- [ ] Revisar os 5 artigos iniciais e a política de privacidade com o responsável técnico.
- [ ] Configurar o GA4 e o perfil no Google Meu Negócio.
