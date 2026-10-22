# Status do projeto (passagem entre sessões)

Última atualização: 2026-10-22. Registro do que já foi feito e do que está
pendente, para que uma nova sessão (humana ou do Claude) continue daqui.

## Situação atual

- **Versão oficial do site:** WordPress + Elementor, com deploy via Docker
  no EasyPanel (VPS Hostinger). Veja [`DEPLOY_EASYPANEL.md`](DEPLOY_EASYPANEL.md).
- **`main` é o branch principal.** Em 2026-10-22 o `main` (que só tinha o
  commit inicial) foi avançado (fast-forward) até o conteúdo de
  `claude/hopeful-newton-4fxwxv`. Nada foi perdido.
- **EasyPanel:** projeto `gestao-docs`, serviço `site` (tipo App), fonte
  GitHub `contabilidade-01/nescon-site`, build por `Dockerfile` na raiz.
  O campo **Ramo** ainda aponta para `claude/hopeful-newton-4fxwxv`;
  **falta trocar para `main`** (ver pendências).

### Branches

| Branch | Conteúdo | Destino |
|---|---|---|
| `main` | Site WordPress (oficial) | Manter |
| `claude/hopeful-newton-4fxwxv` | Igual ao `main` | Apagar depois que o EasyPanel usar `main` |
| `claude/gallant-turing-11oeja` | Versão alternativa em Astro + Netlify, nunca publicada no EasyPanel | Decidir: arquivar ou apagar |

## Acesso ao wp-admin

- O WordPress **do container do EasyPanel** tem um único usuário:
  `admin` (ID 1, e-mail `contato@nesconcontabilidade.com.br`).
- A senha foi redefinida pelo console do EasyPanel para um valor
  **temporário**, que precisa ser trocado no primeiro acesso:
  ```bash
  wp user update admin --user_pass='SENHA-FORTE' --allow-root --skip-email
  ```
  Use `--skip-email`: o container não tem `sendmail`, por isso o aviso
  `sh: 1: /usr/sbin/sendmail: not found`, que é inofensivo. Pelo mesmo
  motivo, o "Esqueceu a senha?" da tela de login não funciona.
- Depois, atualize `WORDPRESS_ADMIN_PASSWORD` na aba **Ambiente** do
  EasyPanel. Essa variável só vale na primeira instalação: mudá-la depois
  **não** altera a senha do WordPress.

## Problema em aberto: login diz "o usuário admin não está cadastrado"

Ao tentar entrar no `/wp-admin` pelo navegador, aparece o erro "o usuário
**admin** não está cadastrado neste site". Mas o `wp user list` no
container mostra que o `admin` existe.

**Hipótese principal:** o endereço aberto no navegador é atendido por
**outro WordPress**, provavelmente uma hospedagem antiga da Hostinger
(hPanel), e não pelo container do EasyPanel. Em outras palavras, o DNS do
domínio não aponta para a VPS, ou o domínio não está ligado ao serviço
`site` no EasyPanel.

Para confirmar, colete:

1. No console `>_` do serviço `site`:
   ```bash
   wp option get siteurl --allow-root
   wp option get home --allow-root
   ```
2. Os domínios cadastrados na aba **Domínios** do serviço `site`.
3. O endereço exato da tela de login no navegador.
4. Se o login com o e-mail `contato@nesconcontabilidade.com.br` também
   falha com "e-mail não cadastrado" (nesse caso, é outro WordPress).

Para cada resultado:

- **O `siteurl` difere do endereço usado no navegador:** entrar pelo
  endereço do `siteurl`, ou pelo domínio configurado no EasyPanel.
- **O domínio aponta para outro servidor:** apontar o registro `A` do
  domínio para o IP da VPS, cadastrar o domínio no serviço `site` com
  HTTPS e, se preciso, ajustar o endereço:
  ```bash
  wp option update home 'https://DOMINIO' --allow-root
  wp option update siteurl 'https://DOMINIO' --allow-root
  ```

## Pendências

1. [ ] Resolver o problema de login acima e trocar a senha temporária.
2. [ ] Atualizar `WORDPRESS_ADMIN_PASSWORD` no EasyPanel.
3. [ ] No EasyPanel, trocar o **Ramo** para `main`, salvar e implantar.
4. [ ] Apagar `claude/hopeful-newton-4fxwxv` depois do passo 3.
5. [ ] Decidir o destino da versão em Astro (`claude/gallant-turing-11oeja`).
6. [ ] Instalar o Elementor Pro (plugin pago), conforme a seção 6 de
   `DEPLOY_EASYPANEL.md`.

## Melhorias sugeridas no provisionamento (não implementadas)

Pontos observados ao revisar `docker/provision.sh`:

- **Não espera o banco.** O entrypoint oficial não aguarda o MySQL. Se o
  banco demorar ou estiver inacessível, o script falha (`set -e`) e o
  container reinicia em loop sem subir o Apache. Sugestão: esperar o banco
  com algumas tentativas e iniciar o Apache mesmo se o provisionamento
  falhar.
- **Arquivos criados como root.** Os plugins e temas instalados pelo WP-CLI
  (`--allow-root`) ficam com dono `root`, então instalar ou atualizar
  plugins pelo wp-admin pode pedir credenciais FTP. Isso afeta, por
  exemplo, o envio do Elementor Pro. Sugestão: rodar
  `chown -R www-data:www-data /var/www/html/wp-content` ao fim do
  provisionamento.
- **URL fixa da instalação.** `WORDPRESS_SITE_URL` só é aplicada na
  primeira instalação. Sugestão: sincronizar `home`/`siteurl` com a
  variável a cada boot.
- **Sem envio de e-mail.** Configurar SMTP (plugin como WP Mail SMTP) para
  recuperação de senha e para o formulário de contato.
