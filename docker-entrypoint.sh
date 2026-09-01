#!/bin/sh
# NNF has no database -- threads, sub-forums, "config.php" and the "users" folder are just files. In this image the
# code lives at "/var/www/nnf" (outside the web-root) and all of that mutable data lives in the web-root
# "/var/www/html" == "$NNF_DATA_DIR", which is meant to be a mounted volume. This script only ever touches that data
# directory: it never writes into the code tree, and it never deletes anything a previous run (or an admin) put here.
set -e

: "${NNF_DATA_DIR:=/var/www/html}"

mkdir -p "$NNF_DATA_DIR/users"

# the rewrite rules have to be read from the web-root, so the Docker ".htaccess" is (re)placed on every start --
# it's code, not data, and carries no per-site state
cp /var/www/nnf/.htaccess.docker "$NNF_DATA_DIR/.htaccess"

# seed "config.php" once, so a fresh volume works out of the box; never overwrite an existing one
[ -f "$NNF_DATA_DIR/config.php" ] || cp /var/www/nnf/config.default.php "$NNF_DATA_DIR/config.php"

chown -R www-data:www-data "$NNF_DATA_DIR"

exec "$@"
