# Deploy na VPS Hostinger com EasyPanel

Este guia assume que você já tem uma **VPS Hostinger com EasyPanel
instalado** (o próprio painel oferece um template de instalação do
EasyPanel na criação da VPS). O deploy é feito via Docker: o EasyPanel
builda a imagem definida no `Dockerfile` deste repositório e a mantém
rodando, atualizando automaticamente a cada push no GitHub.

## Como funciona

- `Dockerfile` — imagem própria baseada na imagem oficial `wordpress`, já
  com o tema `nescon` embutido.
- `docker-compose.yml` — define dois serviços: `wordpress` (a imagem
  acima) e `db` (MySQL).
- `docker/provision.sh` — roda automaticamente toda vez que o container
  sobe: instala o WordPress (se ainda não instalado), instala/ativa
  OceanWP + Elementor + Contact Form 7, ativa o tema `nescon`, cria o
  menu principal e importa o template da página **Planos**.

Ou seja: **você não precisa acessar o wp-admin manualmente para configurar
nada disso** — só para trocar textos/imagens depois.

## 1. Conectar o repositório no EasyPanel

1. No EasyPanel, crie um novo **Project** (ex.: `nescon`).
2. Dentro do projeto, clique em **+ Service > App** (ou **Compose**, se seu
   EasyPanel oferecer esse tipo de serviço — ambos funcionam, veja a nota
   abaixo).
3. Em **Source**, escolha **GitHub** e autorize o EasyPanel a acessar o
   repositório `contabilidade-01/nescon-site`.
4. Selecione o branch que você quer publicar (ex.: `main`, ou o branch
   atual `claude/hopeful-newton-4fxwxv` enquanto testa).
5. Em **Build**, escolha **Dockerfile** (o EasyPanel detecta o
   `Dockerfile` na raiz do repositório automaticamente).
6. Ative **Auto Deploy** — assim, todo `git push` no branch escolhido
   dispara um novo build e deploy automaticamente.

> **Nota sobre "App" vs "Compose":** se seu EasyPanel tiver o tipo de
> serviço **Compose**, você pode apontar direto para o
> `docker-compose.yml` deste repositório, o que já cria o serviço
> `wordpress` **e** o `db` (MySQL) juntos. Se só tiver **App** (um único
> container por serviço), crie dois serviços separados: um **App** a
> partir deste repositório (Dockerfile) para o WordPress, e um serviço de
> **template MySQL** pronto do próprio EasyPanel para o banco — nesse
> caso, ajuste as variáveis `WORDPRESS_DB_*` abaixo com o host/usuário/
> senha que o EasyPanel gerar para o MySQL.

## 2. Variáveis de ambiente

Na aba **Environment** do serviço `wordpress`, cadastre:

| Variável                     | Exemplo                              |
|-------------------------------|---------------------------------------|
| `WORDPRESS_DB_HOST`          | `db:3306` (nome do serviço MySQL no EasyPanel + porta) |
| `WORDPRESS_DB_NAME`          | `nescon`                              |
| `WORDPRESS_DB_USER`          | `nescon`                              |
| `WORDPRESS_DB_PASSWORD`      | *(senha forte — mesma do serviço MySQL)* |
| `WORDPRESS_SITE_URL`         | `https://www.nescon.com.br`           |
| `WORDPRESS_SITE_TITLE`       | `Nescon Contabilidade`                |
| `WORDPRESS_ADMIN_USER`       | *(usuário admin que você vai usar)*   |
| `WORDPRESS_ADMIN_PASSWORD`   | *(senha forte — troque depois do primeiro login)* |
| `WORDPRESS_ADMIN_EMAIL`      | *(seu e-mail)*                        |

Se estiver usando o `docker-compose.yml` como Compose service, o EasyPanel
geralmente lê essas variáveis do arquivo `.env` do projeto ou de um campo
"Environment Variables" na UI — cadastre lá, **nunca** commite senhas no
repositório (o `.gitignore` já bloqueia arquivos `.env`).

## 3. Domínio e SSL

Na aba **Domains** do serviço `wordpress`:

1. Adicione o domínio (`www.nescon.com.br`) ou subdomínio que vai apontar
   para o site.
2. Aponte o DNS do domínio para o IP da VPS Hostinger (registro `A`),
   caso ainda não esteja apontado.
3. Ative **HTTPS** — o EasyPanel emite certificado Let's Encrypt
   automaticamente.
4. Certifique-se de que `WORDPRESS_SITE_URL` (variável de ambiente) usa
   exatamente essa URL com `https://` — é ela que o WordPress grava como
   `siteurl`/`home` na instalação automática.

## 4. Deploy

Clique em **Deploy** (ou apenas dê `git push` no branch conectado, se
Auto Deploy estiver ativo). O EasyPanel vai:

1. Clonar o repositório e buildar a imagem (`docker build .`).
2. Subir o container `wordpress` (e `db`, se for Compose).
3. Na primeira subida, `docker/provision.sh` instala o WordPress do zero
   e importa a página Planos — isso leva alguns segundos a mais que os
   deploys seguintes.

Acompanhe os logs do serviço na própria UI do EasyPanel (aba **Logs**)
para confirmar as mensagens `[nescon] ...` do script de provisionamento.

## 5. Como acessar o wp-admin

Depois do primeiro deploy bem-sucedido, acesse:

```
https://SEU-DOMINIO/wp-admin
```

e entre com o `WORDPRESS_ADMIN_USER` / `WORDPRESS_ADMIN_PASSWORD` que você
cadastrou nas variáveis de ambiente. A página **Planos** já estará
publicada e definida como página inicial.

> Se você **ainda não tem domínio apontado**, pode acessar temporariamente
> pelo IP da VPS + porta que o EasyPanel expôs para o serviço (visível na
> aba do serviço, em **Access** ou **Ports**), mas o ideal é configurar o
> domínio antes do primeiro deploy — trocar `WORDPRESS_SITE_URL` depois
> exige atualizar as opções `siteurl`/`home` do WordPress manualmente.

## 6. Elementor Pro

O template usa três widgets do **Elementor Pro** (menu de navegação,
formulário e tabela de preços) — é um plugin pago, que não pode ser
baixado automaticamente. Depois do primeiro deploy:

1. Baixe o `.zip` do Elementor Pro na sua conta Elementor.
2. Em **wp-admin > Plugins > Adicionar novo > Enviar plugin**, envie o
   `.zip` e ative.
3. A página Planos, importada antes da ativação, passa a renderizar os
   widgets Pro corretamente assim que o plugin é ativado — não precisa
   reimportar nada.

## 7. Rastreamento (Analytics/Ads/Pixel)

Configure suas próprias contas (Google Tag Manager, Meta Pixel, etc.)
depois do primeiro acesso ao wp-admin — veja a seção 6 de
[`docs/INSTALL.md`](INSTALL.md) para o porquê de isso não vir
pré-configurado.
