FROM php:8.3-apache

# bash + GNU userland for PHP shell_exec() calls and the .scr scripts
RUN apt-get update && apt-get install -y --no-install-recommends \
        bash coreutils findutils grep sed gawk diffutils util-linux \
        curl wget ca-certificates zip unzip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod headers deflate expires

# Same layout as `php -S` run from mary/: docroot is mary/, app at /thelady/music.html
COPY apache.conf /etc/apache2/conf-available/mary.conf
RUN a2enconf mary

COPY . /var/www/html/
RUN chmod +x /var/www/html/*.scr 2>/dev/null || true \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s CMD curl -fs http://localhost/thelady/music.html -o /dev/null || exit 1
