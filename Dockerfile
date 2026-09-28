# Imagem própria do site da Nescon: WordPress oficial + tema Nescon +
# scripts de provisionamento (instala Elementor/OceanWP e importa o
# template da página "Planos" no primeiro boot).
FROM wordpress:php8.3-apache

# WP-CLI, usado pelo script de provisionamento.
RUN curl -o /usr/local/bin/wp -fsSL \
        https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp \
    && wp --info --allow-root

# Semeia nosso tema filho em /usr/src/wordpress: é de lá que o entrypoint
# oficial da imagem "wordpress" copia os arquivos para /var/www/html (o
# volume persistente) na primeira execução — o mesmo mecanismo usado para
# os temas padrão do WordPress.
COPY wp-content/themes/nescon/ /usr/src/wordpress/wp-content/themes/nescon/

# Guardamos o template do Elementor e os scripts de provisionamento fora
# de /var/www/html (que é um volume) para que sempre estejam disponíveis,
# mesmo em um volume antigo já existente.
COPY wp-content/elementor-templates/ /usr/local/share/nescon/elementor-templates/
COPY docker/import-template.php /usr/local/bin/nescon-import-template.php
COPY docker/provision.sh /usr/local/bin/apache2-nescon-provision
RUN chmod +x /usr/local/bin/apache2-nescon-provision

# O entrypoint oficial prepara os arquivos e o wp-config.php somente quando
# o comando começa com apache2 (ou é php-fpm). Preserve esse prefixo no
# CMD para executar o provisionamento após essa preparação, finalizando
# com o Apache. O entrypoint não aguarda a disponibilidade do banco.
CMD ["apache2-nescon-provision"]
