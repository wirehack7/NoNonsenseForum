#!/usr/bin/env bash
# Manage the forum's access-gate passwords ("users/access.txt" -- see INSTALL.txt 2.6).
# Any ONE listed password lets a visitor in; each entry carries a "# label" so you can
# tell which is which when revoking.
#
#   ./manage-access.sh add "Alice"              generate a password for Alice, hash it, add it
#   ./manage-access.sh add "meetup" hunter2     add a password you chose
#   ./manage-access.sh add --plain "Bob"        store it in plain text (not hashed)
#   ./manage-access.sh list                     show the labels (never prints the passwords)
#   ./manage-access.sh remove 2                 revoke entry #2 from the list
#
# Config via environment:
#   DATA_DIR      (default <script dir>/data)   -- NNF's data directory
#   COMPOSE_FILE  (default <script dir>/docker-compose.tor.yml, else docker-compose.yml)
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
DATA_DIR=${DATA_DIR:-$SCRIPT_DIR/data}
ACCESS_FILE=$DATA_DIR/users/access.txt

if [ -z "${COMPOSE_FILE:-}" ]; then
	COMPOSE_FILE=$SCRIPT_DIR/docker-compose.tor.yml
	[ -f "$COMPOSE_FILE" ] || COMPOSE_FILE=$SCRIPT_DIR/docker-compose.yml
fi

die () { echo "error: $*" >&2; exit 1; }

# hash a password with bcrypt, trying php / the running container / htpasswd in turn
hash_pw () {
	local pw=$1
	if command -v php >/dev/null 2>&1; then
		printf '%s' "$pw" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_BCRYPT);'
	elif command -v docker >/dev/null 2>&1 && [ -f "$COMPOSE_FILE" ] \
	     && docker compose -f "$COMPOSE_FILE" ps forum 2>/dev/null | grep -Eq 'running|Up'; then
		printf '%s' "$pw" | docker compose -f "$COMPOSE_FILE" exec -T forum \
			php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_BCRYPT);'
	elif command -v htpasswd >/dev/null 2>&1; then
		htpasswd -nbBC 12 x "$pw" | cut -d: -f2
	else
		return 1
	fi
}

# the non-comment entries, in order, as "N<TAB>label"
entries () {
	[ -f "$ACCESS_FILE" ] || return 0
	local n=0 line stripped label
	while IFS= read -r line || [ -n "$line" ]; do
		stripped=$(printf '%s' "$line" | sed 's/[[:space:]]*#.*$//; s/[[:space:]]*$//; s/^[[:space:]]*//')
		case "$stripped" in ''|'#'*) continue ;; esac
		n=$((n + 1))
		label=$(printf '%s' "$line" | sed -n 's/.*[[:space:]]#[[:space:]]*//p')
		printf '%d\t%s\n' "$n" "${label:-(no label)}"
	done < "$ACCESS_FILE"
}

cmd=${1:-}; shift || true

case "$cmd" in
add)
	plain=0
	[ "${1:-}" = "--plain" ] && { plain=1; shift; }
	label=${1:-}
	pw=${2:-}
	[ -n "$label" ] || die 'usage: manage-access.sh add [--plain] "<label>" [password]'

	if [ -z "$pw" ]; then
		pw=$(head -c 18 /dev/urandom | base64 | tr '+/' '-_' | tr -d '=')
		generated=1
	else
		generated=0
	fi

	if [ "$plain" = 1 ]; then
		stored=$pw
		case "$pw" in *' #'*) die "a plain-text password can't contain ' #' -- use a hash";; esac
	elif ! stored=$(hash_pw "$pw"); then
		die "no way to hash found (need php, a running 'forum' container, or htpasswd). Use --plain to store as-is."
	fi

	mkdir -p "$(dirname "$ACCESS_FILE")"
	printf '%s  # %s (added %s)\n' "$stored" "$label" "$(date +%Y-%m-%d)" >> "$ACCESS_FILE"

	echo
	echo "added entry for: $label"
	if [ "$generated" = 1 ]; then
		echo
		echo "    password:  $pw"
		echo
		echo "hand this to the person now -- it is not stored in plain text and won't be shown again."
	fi
	;;

list)
	out=$(entries)
	[ -n "$out" ] || { echo "no access passwords set (the gate is off)"; exit 0; }
	printf '%s\n' "$out" | while IFS=$'\t' read -r n label; do printf '%3d  %s\n' "$n" "$label"; done
	;;

remove)
	target=${1:-}
	case "$target" in ''|*[!0-9]*) die "usage: manage-access.sh remove <number>  (see 'list')";; esac
	[ -f "$ACCESS_FILE" ] || die "no $ACCESS_FILE"

	label=$(entries | sed -n "${target}p" | cut -f2-)
	[ -n "$label" ] || die "no entry #$target (see 'list')"
	printf 'remove entry #%s (%s)? [y/N] ' "$target" "$label"
	read -r ans; case "$ans" in y|Y|yes) ;; *) echo aborted; exit 1;; esac

	tmp=$(mktemp); n=0
	while IFS= read -r line || [ -n "$line" ]; do
		stripped=$(printf '%s' "$line" | sed 's/[[:space:]]*#.*$//; s/[[:space:]]*$//; s/^[[:space:]]*//')
		case "$stripped" in ''|'#'*) printf '%s\n' "$line" >> "$tmp"; continue ;; esac
		n=$((n + 1))
		[ "$n" = "$target" ] || printf '%s\n' "$line" >> "$tmp"
	done < "$ACCESS_FILE"
	mv "$tmp" "$ACCESS_FILE"
	echo "removed #$target ($label) -- anyone who used that password is locked out on their next request."
	;;

*)
	sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
	exit 1
	;;
esac
