#!/usr/bin/env bash
# One-shot deploy of NoNonsense Forum as a hidden-only Tor onion service on a
# fresh Ubuntu (22.04 / 24.04) server. Installs Docker + Tor, clones this repo,
# brings up the loopback-only compose stack, and creates the v3 onion services.
#
# Run as root on the server:
#   curl -fsSLO https://raw.githubusercontent.com/wirehack7/NoNonsenseForum/master/tools/deploy-onion.sh
#   sudo bash deploy-onion.sh
#
# This gets the service RUNNING. It does NOT, by default, apply the full
# lock-down (default-deny firewall, SSH-only-over-onion, anonymous provisioning,
# OpSec) -- read TOR.md and do that once you've confirmed the SSH onion works.
# Set HARDEN=1 to also apply the firewall (this WILL drop a clear-net SSH
# session -- only do it once you can reach the box over the SSH onion).
#
# To UPDATE an already-deployed forum later, use 'tools/update-onion.sh'.
#
# Config via environment:
#   REPO_URL     (default https://github.com/wirehack7/NoNonsenseForum.git)
#   REPO_BRANCH  (default master)
#   TARGET_DIR   (default /opt/nnf)
#   SSH_ONION    (default 1 -- also create an onion for SSH admin access)
#   HARDEN       (default 0 -- apply the default-deny nftables lockdown)
set -euo pipefail

REPO_URL=${REPO_URL:-https://github.com/wirehack7/NoNonsenseForum.git}
REPO_BRANCH=${REPO_BRANCH:-master}
TARGET_DIR=${TARGET_DIR:-/opt/nnf}
SSH_ONION=${SSH_ONION:-1}
HARDEN=${HARDEN:-0}

TORRC=/etc/tor/torrc
TOR_USER=debian-tor

die () { echo "error: $*" >&2; exit 1; }
log () { echo; echo "==> $*"; }

[ "$(id -u)" = 0 ] || die "run as root"
. /etc/os-release
[ "${ID:-}" = ubuntu ] || echo "warning: tested on Ubuntu, found '${ID:-?}' -- continuing anyway"

# --------------------------------------------------------------------------- deps
log "installing base packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg git nftables

if ! command -v docker >/dev/null; then
	log "installing Docker (official apt repo)"
	install -m0755 -d /etc/apt/keyrings
	curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
		| gpg --dearmor -o /etc/apt/keyrings/docker.gpg
	chmod a+r /etc/apt/keyrings/docker.gpg
	echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
		> /etc/apt/sources.list.d/docker.list
	apt-get update -qq
	apt-get install -y -qq docker-ce docker-ce-cli containerd.io \
		docker-buildx-plugin docker-compose-plugin
	systemctl enable --now docker
else
	log "Docker already installed -- skipping"
fi

if ! command -v tor >/dev/null; then
	log "installing Tor (official apt repo)"
	install -m0755 -d /etc/apt/keyrings
	curl -fsSL https://deb.torproject.org/torproject.org/A3C4F0F979CAA22CDBA8F512EE8CBC9E886DDD89.asc \
		| gpg --dearmor -o /etc/apt/keyrings/tor.gpg
	cat > /etc/apt/sources.list.d/tor.list <<EOF
deb     [signed-by=/etc/apt/keyrings/tor.gpg] https://deb.torproject.org/torproject.org ${VERSION_CODENAME} main
deb-src [signed-by=/etc/apt/keyrings/tor.gpg] https://deb.torproject.org/torproject.org ${VERSION_CODENAME} main
EOF
	apt-get update -qq
	apt-get install -y -qq tor deb.torproject.org-keyring
else
	log "Tor already installed -- skipping"
fi

# ------------------------------------------------------------------------- clone
if [ -d "$TARGET_DIR/.git" ]; then
	# never touch an existing checkout -- it may hold local edits (the §5.2 Host
	# filter, a scrubbed theme). update it yourself with git when you mean to.
	log "checkout already exists at $TARGET_DIR -- leaving it as-is"
else
	log "cloning $REPO_URL ($REPO_BRANCH) -> $TARGET_DIR"
	git clone --depth 1 -b "$REPO_BRANCH" "$REPO_URL" "$TARGET_DIR"
fi
[ -f "$TARGET_DIR/docker-compose.tor.yml" ] \
	|| die "docker-compose.tor.yml not in the checkout -- wrong REPO_BRANCH? (try REPO_BRANCH=development)"

# --------------------------------------------------------------- onion services
add_hs () {
	local marker=$1; shift
	if grep -qF "$marker" "$TORRC"; then
		log "torrc already has $marker -- skipping"
		return
	fi
	log "adding $marker to $TORRC"
	{ echo; echo "$marker"; printf '%s\n' "$@"; } >> "$TORRC"
}

add_hs "# NNF-FORUM-HS (managed by deploy-onion.sh)" \
	"HiddenServiceDir /var/lib/tor/nnf/" \
	"HiddenServicePort 80 127.0.0.1:8080" \
	"HiddenServiceVersion 3" \
	"HiddenServiceEnableIntroDoSDefense 1" \
	"HiddenServiceEnableIntroDoSRatePerSec 25" \
	"HiddenServiceEnableIntroDoSBurstPerSec 200"

if [ "$SSH_ONION" = 1 ]; then
	add_hs "# NNF-SSH-HS (managed by deploy-onion.sh)" \
		"HiddenServiceDir /var/lib/tor/ssh/" \
		"HiddenServicePort 22 127.0.0.1:22" \
		"HiddenServiceVersion 3"
fi

log "starting Tor"
systemctl enable --now tor >/dev/null 2>&1 || true
systemctl restart tor@default

wait_hostname () {
	local d=$1 i=0
	while [ ! -s "$d/hostname" ]; do
		i=$((i + 1)); [ "$i" -gt 30 ] && die "Tor did not create $d/hostname"
		sleep 1
	done
	tr -d '[:space:]' < "$d/hostname"
}
FORUM_ONION=$(wait_hostname /var/lib/tor/nnf)
[ "$SSH_ONION" = 1 ] && SSH_ONION_ADDR=$(wait_hostname /var/lib/tor/ssh) || SSH_ONION_ADDR=

# ---------------------------------------------------------------------- the app
log "building and starting the forum (loopback-only)"
cd "$TARGET_DIR"
docker compose -f docker-compose.tor.yml up -d --build

# sanity: the port must be on loopback only
if docker compose -f docker-compose.tor.yml port forum 80 2>/dev/null | grep -q '^0\.0\.0\.0\|^\[::\]'; then
	die "forum port is published on a public interface -- check docker-compose.tor.yml"
fi

# ---------------------------------------------------------------- optional harden
if [ "$HARDEN" = 1 ]; then
	log "HARDEN=1 -- applying default-deny firewall in 15s (Ctrl-C to abort)"
	echo "    a clear-net SSH session WILL be dropped; reconnect over ${SSH_ONION_ADDR:-the SSH onion}"
	sleep 15
	cat > /etc/nftables.conf <<'EOF'
#!/usr/sbin/nft -f
flush ruleset
table inet filter {
	chain input {
		type filter hook input priority 0; policy drop;
		iif "lo" accept
		ct state established,related accept
		ct state invalid drop
	}
	chain forward { type filter hook forward priority 0; policy drop; }
	chain output { type filter hook output priority 0; policy accept; }
}
EOF
	systemctl enable --now nftables
	nft -f /etc/nftables.conf
	if [ "$SSH_ONION" = 1 ]; then
		echo "ListenAddress 127.0.0.1" > /etc/ssh/sshd_config.d/99-onion.conf
		systemctl restart ssh
	fi
fi

# -------------------------------------------------------------------------- done
cat <<EOF

============================================================================
 NoNonsense Forum is up as a Tor onion service.

   forum : http://$FORUM_ONION/
$( [ -n "$SSH_ONION_ADDR" ] && echo "   ssh   : $SSH_ONION_ADDR  (ssh -o ProxyCommand='nc -X 5 -x 127.0.0.1:9050 %h %p' user@$SSH_ONION_ADDR)" )

 checkout : $TARGET_DIR
 data     : $TARGET_DIR/data   (config.php, users/, threads -- the whole forum)
 keys     : /var/lib/tor/nnf/  <-- BACK THIS UP ENCRYPTED, OFFLINE

 NOT done automatically -- read TOR.md:
$( [ "$HARDEN" = 1 ] || echo "   * firewall lockdown  (re-run with HARDEN=1 once the SSH onion works)" )
   * FORUM_HTTPS=false and a non-identifying FORUM_NAME in $TARGET_DIR/data/config.php
   * Host-header filter in .htaccess.docker (§5.2), then: docker compose -f docker-compose.tor.yml up -d --build
   * scrub identifying strings from the theme (§5.3)
   * verify from outside: nmap / curl the server IP -> nothing answers (§7)
============================================================================
EOF
