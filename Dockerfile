# NoNonsense Forum has no database and no build step -- this image is just PHP + Apache configured the way NNF
# needs (mod_rewrite for its "pretty" URLs, ".htaccess" support, and the "mbstring" / "intl" extensions it uses for
# UTF-8 safety and transliterating post titles into filenames).
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libicu-dev \
    && docker-php-ext-install mbstring intl \
    && apt-get purge -y --auto-remove libonig-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# NNF relies on ".htaccess" for pretty URLs, and to keep the "users" folder (post credentials) from being served
# directly -- both `mod_rewrite` and `AllowOverride All` are required for it to work as intended
RUN a2enmod rewrite headers expires deflate \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# the code is staged here, *not* copied straight into /var/www/html: NNF saves new threads, sub-forums, and user
# credential files directly into its own web-root (it has no database -- see README.txt), so /var/www/html is
# meant to be a persistent volume. 'docker-entrypoint.sh' copies this staged code into that volume on every
# container start, which both seeds it on a first run and keeps the code up to date on later ones, without ever
# touching data that's already there.
# only NNF's own application files are staged (the same set the project's own ".gitignore" whitelists) -- not the
# Dockerfile, this entrypoint script, CI config, or other dev tooling, none of which have any business being
# copied into, and thus served out of, the web-root
COPY .htaccess robots.txt favicon.default.ico apple-touch-icon.default.png metro-tile.default.png \
     config.default.php index.php start.php thread.php markup.php privacy.php search.php \
     LICENCE.txt README.txt INSTALL.txt HISTORY.txt \
     /usr/src/nnf/
# directories have to be copied individually -- `COPY` copies a directory *source*'s contents into the destination
# rather than the directory itself, so mixing them into the multi-source COPY above would flatten them
COPY lib    /usr/src/nnf/lib/
COPY themes /usr/src/nnf/themes/
COPY users  /usr/src/nnf/users/
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 80
