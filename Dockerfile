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

WORKDIR /var/www/html
COPY . /var/www/html/

# the forum saves new threads, sub-forums and user credential files into its own folder, so the web server user
# needs write access there, not just read access
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80
