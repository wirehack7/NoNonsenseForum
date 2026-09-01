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

chown -R www-data:www-data "$NNF_DATA_DIR" 2>/dev/null \
	|| echo "nnf: note: could not chown $NNF_DATA_DIR (rootless Docker / userns-remap?) -- continuing" >&2

# make sure the web user can actually write there; posting and sign-in fail silently to the visitor otherwise
if ! su -s /bin/sh -c "test -w '$NNF_DATA_DIR' && test -w '$NNF_DATA_DIR/users'" www-data; then
	cat >&2 <<EOF
nnf: ERROR: $NNF_DATA_DIR is not writable by the web server (user www-data).
     Posting new threads and signing in will fail until this is fixed. Usual causes:
       * SELinux host: add ":z" to the volume in docker-compose*.yml
             - ./data:/var/www/html:z
       * the host directory is owned by a user the container can't take over
         (rootless Docker / userns-remap): chown it to UID 33 on the host, or
         run the container with 'user: "0:0"'.
       * the mount is read-only.
EOF
fi

exec "$@"
