#!/bin/bash
# Provisiona o WordPress da Nescon no primeiro boot (idempotente: pode
# rodar em todo restart do container sem duplicar nada) e depois inicia o
# Apache em primeiro plano.
set -euo pipefail

cd /var/www/html
WP="wp --allow-root --path=/var/www/html"

: "${WORDPRESS_ADMIN_USER:?Defina WORDPRESS_ADMIN_USER}"
: "${WORDPRESS_ADMIN_PASSWORD:?Defina WORDPRESS_ADMIN_PASSWORD}"
: "${WORDPRESS_ADMIN_EMAIL:?Defina WORDPRESS_ADMIN_EMAIL}"
: "${WORDPRESS_SITE_URL:?Defina WORDPRESS_SITE_URL (ex.: https://www.nescon.com.br)}"
SITE_TITLE="${WORDPRESS_SITE_TITLE:-Nescon Contabilidade}"

if $WP core is-installed 2>/dev/null; then
    echo "[nescon] WordPress já instalado, pulando core install."
else
    echo "[nescon] Instalando o WordPress..."
    $WP core install \
        --url="$WORDPRESS_SITE_URL" \
        --title="$SITE_TITLE" \
        --admin_user="$WORDPRESS_ADMIN_USER" \
        --admin_password="$WORDPRESS_ADMIN_PASSWORD" \
        --admin_email="$WORDPRESS_ADMIN_EMAIL" \
        --skip-email
fi

echo "[nescon] Garantindo tema/plugins..."
$WP theme is-installed oceanwp || $WP theme install oceanwp
$WP plugin is-installed elementor || $WP plugin install elementor
$WP plugin is-active elementor || $WP plugin activate elementor
$WP plugin is-installed contact-form-7 || $WP plugin install contact-form-7
$WP plugin is-active contact-form-7 || $WP plugin activate contact-form-7
$WP theme is-active nescon || $WP theme activate nescon

echo "[nescon] Garantindo menu principal..."
if $WP menu list --fields=slug --format=csv | tail -n +2 | grep -qx "main-menu"; then
    MENU_ID=$($WP menu list --fields=term_id,slug --format=csv | tail -n +2 \
        | awk -F, '$2=="main-menu"{print $1}')
else
    MENU_ID=$($WP menu create "main-menu" --porcelain)
fi
$WP menu location assign "main-menu" main-menu || true
export NESCON_MENU_ID="$MENU_ID"

echo "[nescon] Importando template Elementor da página Planos..."
$WP eval-file /usr/local/bin/nescon-import-template.php || \
    echo "[nescon] Aviso: falha ao importar o template (importe manualmente depois, ver docs/DEPLOY_EASYPANEL.md)."

echo "[nescon] Provisionamento concluído. Iniciando Apache."
exec apache2-foreground
