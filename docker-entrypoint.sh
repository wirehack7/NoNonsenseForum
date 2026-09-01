#!/bin/sh
# NNF has no database -- threads, sub-forums, "config.php" and the "users" folder are just files, stored right
# alongside the application code itself (see README.txt). That means the volume persisting NNF's data has to be
# the whole web-root, /var/www/html -- there's no separate "data" folder to mount instead.
#
# To keep that volume's application code in sync with this image's version (e.g. after a fresh `docker compose
# up` with a rebuilt image) without ever touching anything else already in the volume -- a previously-posted
# thread, "config.php", a customised theme file, the "users" folder -- this copies the code staged at
# '/usr/src/nnf' (see 'Dockerfile') on top of it. `cp -a` only overwrites/adds files that exist in the source;
# it never deletes files that only exist in the destination, so user data is always left alone.
set -e

cp -a /usr/src/nnf/. /var/www/html/
chown -R www-data:www-data /var/www/html

exec "$@"
