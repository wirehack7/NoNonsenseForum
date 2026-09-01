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

# NNF writes threads, sub-forums, "config.php" and the "users" folder straight into its own working directory (it has
# no database -- see README.txt). To keep that mutable data completely separate from the code, the code lives OUTSIDE
# the web-root here, at "/var/www/nnf", and is reached over the web through the "Alias /_nnf" in "nnf.conf". The
# web-root "/var/www/html" stays empty in the image and is meant to be a mounted volume holding data only.
# `NNF_DATA_DIR` tells 'start.php' where that data root is (see the `FORUM_DATA` constant there).
ENV NNF_DATA_DIR=/var/www/html
# the image serves the forum at the container's web-root; 'start.php' can't infer this itself because the code is
# reached through an "Alias" (see "nnf.conf"), so state it explicitly. behind a reverse proxy on a sub-path, override
# this (and see "INSTALL.txt")
ENV NNF_URL_PATH=/

COPY nnf.conf /etc/apache2/conf-available/nnf.conf
RUN a2enconf nnf

COPY .htaccess.docker robots.txt favicon.default.ico apple-touch-icon.default.png metro-tile.default.png \
     config.default.php index.php start.php thread.php markup.php privacy.php search.php \
     LICENCE.txt README.txt INSTALL.txt HISTORY.txt \
     /var/www/nnf/
# directories have to be copied individually -- `COPY` copies a directory *source*'s contents into the destination
# rather than the directory itself, so mixing them into the multi-source COPY above would flatten them
COPY lib    /var/www/nnf/lib/
COPY themes /var/www/nnf/themes/
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 80
