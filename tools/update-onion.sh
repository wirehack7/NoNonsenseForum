#!/usr/bin/env bash
# Update an already-deployed hidden-only NNF (see TOR.md): pull the latest code,
# rebuild the image, recreate the container. The data ("./data") and the onion
# key ("/var/lib/tor/nnf") are never touched.
#
# Run on the HOST, as root, from anywhere inside the checkout:
#
#   sudo tools/update-onion.sh
#   sudo tools/update-onion.sh --no-backup      # skip the ./data snapshot
#
# Config via environment:
#   REPO_DIR      (default: the checkout this script lives in)
#   COMPOSE_FILE  (default <repo>/docker-compose.tor.yml)
#   TOR_HS_DIR    (default /var/lib/tor/nnf)
#   BACKUP_DIR    (default <repo>/../nnf-backups)
set -euo pipefail

REPO_DIR=${REPO_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
COMPOSE_FILE=${COMPOSE_FILE:-$REPO_DIR/docker-compose.tor.yml}
TOR_HS_DIR=${TOR_HS_DIR:-/var/lib/tor/nnf}
BACKUP_DIR=${BACKUP_DIR:-$REPO_DIR/../nnf-backups}

BACKUP=1
[ "${1:-}" = "--no-backup" ] && BACKUP=0

die () { echo "error: $*" >&2; exit 1; }
log () { echo; echo "==> $*"; }

[ "$(id -u)" = 0 ]       || die "run as root (needs docker + the tor key dir)"
command -v docker >/dev/null || die "docker not found"
[ -f "$COMPOSE_FILE" ]  || die "no compose file at $COMPOSE_FILE"
[ -d "$REPO_DIR/.git" ] || die "$REPO_DIR is not a git checkout"
cd "$REPO_DIR"

branch=$(git rev-parse --abbrev-ref HEAD)
[ "$branch" != HEAD ] || die "detached HEAD -- checkout a branch first"
old=$(git rev-parse --short HEAD)

# --------------------------------------------------------------------- backup
if [ "$BACKUP" = 1 ]; then
	mkdir -p "$BACKUP_DIR"
	snap="$BACKUP_DIR/data-$(date +%Y%m%d-%H%M%S).tgz"
	log "backing up ./data -> $snap"
	tar czf "$snap" -C "$REPO_DIR" data
	# keep the 10 most recent
	ls -1t "$BACKUP_DIR"/data-*.tgz 2>/dev/null | tail -n +11 | xargs -r rm -f
fi

# ----------------------------------------------------------------- pull code
log "fetching origin/$branch"
git fetch --quiet origin "$branch"
new=$(git rev-parse --short "origin/$branch")
if [ "$old" = "$new" ]; then
	echo "already up to date ($old) -- rebuilding anyway to pick up base-image updates"
else
	if ! git merge --ff-only "origin/$branch" >/dev/null 2>&1; then
		die "can't fast-forward $branch (local commits or edits). Commit/stash them,
       or maintain your changes on a branch and merge origin/$branch into it yourself,
       then re-run."
	fi
	echo "code: $old -> $new"
	git --no-pager log --oneline "$old..$new" | sed 's/^/    /'
fi

# --------------------------------------------------------------- rebuild
log "rebuilding and recreating the container"
docker compose -f "$COMPOSE_FILE" up -d --build --pull always
docker image prune -f >/dev/null || true

# the entrypoint re-copies ".htaccess" into ./data on start; config.php / users / threads are left alone

# --------------------------------------------------------------- verify
log "checking the container is up"
i=0
until docker compose -f "$COMPOSE_FILE" ps forum 2>/dev/null | grep -Eq 'running|Up'; do
	i=$((i + 1)); [ "$i" -gt 20 ] && die "the 'forum' container did not come up -- check: docker compose -f $COMPOSE_FILE logs forum"
	sleep 1
done

port=$(docker compose -f "$COMPOSE_FILE" port forum 80 2>/dev/null || true)
case "$port" in
	127.0.0.1:*) : ;;
	'')          echo "warning: no published port found for forum:80" ;;
	*)           die "forum port is published on '$port' -- must be 127.0.0.1 only (check $COMPOSE_FILE)" ;;
esac

onion=$( [ -r "$TOR_HS_DIR/hostname" ] && tr -d '[:space:]' < "$TOR_HS_DIR/hostname" || echo '?' )

cat <<EOF

done. forum updated and running.
  version : $(git rev-parse --short HEAD)
  onion   : http://$onion/
$( [ "$BACKUP" = 1 ] && echo "  backup  : $snap" )

if something's wrong:
  docker compose -f $COMPOSE_FILE logs -f forum
  git -C $REPO_DIR reset --hard $old && docker compose -f $COMPOSE_FILE up -d --build   # roll back code
EOF
