#!/bin/bash
# Provisiona o WordPress da Nescon a cada boot (idempotente: pode rodar em
# todo restart do container sem duplicar nada) e depois inicia o Apache em
# primeiro plano. Uma falha no provisionamento é registrada no log, mas não
# impede o Apache de subir: assim o site continua acessível e o container
# não entra em loop de reinício.
set -uo pipefail

cd /var/www/html
WP="wp --allow-root --path=/var/www/html"

# Espera o MySQL aceitar conexões. O entrypoint oficial não faz isso, e o
# banco pode demorar a subir (ou subir depois do site) no EasyPanel.
wait_for_db() {
    local tries="${WORDPRESS_DB_WAIT_TRIES:-30}" i
    for ((i = 1; i <= tries; i++)); do
        if php -r '
            $host = getenv("WORDPRESS_DB_HOST") ?: "mysql";
            $port = 3306;
            if (preg_match("/^(.+):(\d+)$/", $host, $m)) { $host = $m[1]; $port = (int) $m[2]; }
            mysqli_report(MYSQLI_REPORT_OFF);
            $db = @mysqli_connect($host, getenv("WORDPRESS_DB_USER") ?: "", getenv("WORDPRESS_DB_PASSWORD") ?: "", getenv("WORDPRESS_DB_NAME") ?: "", $port);
            exit($db ? 0 : 1);
        '; then
            echo "[nescon] Banco de dados disponível."
            return 0
        fi
        echo "[nescon] Aguardando o banco de dados ($i/$tries)..."
        sleep 2
    done
    echo "[nescon] Erro: banco de dados inacessível em ${WORDPRESS_DB_HOST:-?}." >&2
    return 1
}

provision() {
    set -e

    : "${WORDPRESS_ADMIN_USER:?Defina WORDPRESS_ADMIN_USER}"
    : "${WORDPRESS_ADMIN_PASSWORD:?Defina WORDPRESS_ADMIN_PASSWORD}"
    : "${WORDPRESS_ADMIN_EMAIL:?Defina WORDPRESS_ADMIN_EMAIL}"
    : "${WORDPRESS_SITE_URL:?Defina WORDPRESS_SITE_URL (ex.: https://www.nescon.com.br)}"
    local site_url="${WORDPRESS_SITE_URL%/}"
    local site_title="${WORDPRESS_SITE_TITLE:-Nescon Contabilidade}"

    wait_for_db

    if $WP core is-installed 2>/dev/null; then
        echo "[nescon] WordPress já instalado, pulando core install."
    else
        echo "[nescon] Instalando o WordPress..."
        $WP core install \
            --url="$site_url" \
            --title="$site_title" \
            --admin_user="$WORDPRESS_ADMIN_USER" \
            --admin_password="$WORDPRESS_ADMIN_PASSWORD" \
            --admin_email="$WORDPRESS_ADMIN_EMAIL" \
            --skip-email
    fi

    # Mantém home/siteurl iguais a WORDPRESS_SITE_URL, para que trocar o
    # domínio seja só mudar a variável e reimplantar.
    local opt current
    for opt in home siteurl; do
        current=$($WP option get "$opt")
        if [ "$current" != "$site_url" ]; then
            echo "[nescon] Atualizando $opt: $current -> $site_url"
            $WP option update "$opt" "$site_url"
        fi
    done

    echo "[nescon] Garantindo tema/plugins..."
    $WP theme is-installed oceanwp || $WP theme install oceanwp
    $WP plugin is-installed elementor || $WP plugin install elementor
    $WP plugin is-active elementor || $WP plugin activate elementor
    $WP plugin is-installed contact-form-7 || $WP plugin install contact-form-7
    $WP plugin is-active contact-form-7 || $WP plugin activate contact-form-7
    $WP theme is-active nescon || $WP theme activate nescon

    echo "[nescon] Garantindo menu principal..."
    local menu_id
    if $WP menu list --fields=slug --format=csv | tail -n +2 | grep -qx "main-menu"; then
        menu_id=$($WP menu list --fields=term_id,slug --format=csv | tail -n +2 \
            | awk -F, '$2=="main-menu"{print $1}')
    else
        menu_id=$($WP menu create "main-menu" --porcelain)
    fi
    $WP menu location assign "main-menu" main-menu || true
    export NESCON_MENU_ID="$menu_id"

    echo "[nescon] Importando template Elementor da página Planos..."
    $WP eval-file /usr/local/bin/nescon-import-template.php || \
        echo "[nescon] Aviso: falha ao importar o template (importe manualmente depois, ver docs/DEPLOY_EASYPANEL.md)."
}

# O provisionamento roda em um processo separado: dentro de um "if", o bash
# ignora o "set -e" de uma função, então chamá-la diretamente esconderia
# as falhas.
if [ "${1:-}" = "--provision" ]; then
    provision
    exit
fi

# Plugin "must-use" que envia os e-mails do WordPress por SMTP (variáveis
# SMTP_*). Copiado a cada boot para chegar também a volumes já existentes.
mkdir -p wp-content/mu-plugins
cp /usr/local/share/nescon/mu-plugins/*.php wp-content/mu-plugins/

if "$0" --provision; then
    echo "[nescon] Provisionamento concluído."
else
    echo "[nescon] Erro: o provisionamento falhou (veja as mensagens acima). Iniciando o Apache assim mesmo." >&2
fi

# O WP-CLI roda como root; devolve os arquivos ao usuário do Apache para que
# o wp-admin consiga instalar e atualizar plugins sem pedir FTP.
chown -R www-data:www-data wp-content

echo "[nescon] Iniciando Apache."
exec apache2-foreground
