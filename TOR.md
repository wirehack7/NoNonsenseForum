# Running NoNonsense Forum as a hidden‑only Tor onion service (Docker Compose)

This guide runs NNF **exclusively** as a Tor v3 onion service, using the
project's own Docker image for the forum and running **Tor on the host** (like
Docker itself). The NNF container publishes its web port to loopback only, and
the host's Tor forwards it to the Tor network. The result:

* the machine never answers as a web server on its clear‑net IP — the only
  published port is bound to `127.0.0.1`, so probing the IP directly gives no
  hint that this (or any) forum is hosted there;
* the server's location is not linked to your identity at any point — purchase,
  payment, provisioning, administration, or content;
* NNF itself leaks nothing that would tie the onion site back to a clear‑net
  host, a person, or another site.

> **Honesty.** "100 % anonymous" is a goal, not a guarantee. Anonymity is a
> property of your whole operation over time, not of a compose file. One mistake
> — logging in once over the clear net, reusing an identifier, paying from a
> known account, posting a photo with EXIF — can undo everything below. Read the
> whole document first. For a serious adversary, also read the Tor Project's
> [onion‑service OpSec guide](https://community.torproject.org/onion-services/advanced/opsec/)
> and use [Whonix](https://www.whonix.org/) for the admin workstation.

Builds on `INSTALL.txt` §1.6 / §1.7 and the `FORUM_DATA` split (code and data in
separate directories) already in this repo.

---

## Quick start

The repo ships **`tools/deploy-onion.sh`** — on a fresh Ubuntu 22.04 / 24.04
server, as root, it installs Docker + Tor, clones this repo, brings up the
loopback‑only stack and creates the v3 onion services:

```sh
curl -fsSLO https://raw.githubusercontent.com/wirehack7/NoNonsenseForum/refs/heads/master/tools/deploy-onion.sh
less deploy-onion.sh                # read it before you run it
sudo bash deploy-onion.sh          # prints your .onion when done
# once you have connected over the SSH onion it printed and confirmed it works:
sudo HARDEN=1 bash deploy-onion.sh # applies the default-deny firewall
```

Later, update the running forum with **`sudo tools/update-onion.sh`** (pulls the
code, rebuilds the image, leaves `./data` and the onion key alone).

That gets the service **running**. It does **not** make the operation
*anonymous* — the server still has to be acquired, paid for, and administered
without linking it to you, and NNF still has to be configured and scrubbed so it
leaks nothing. The rest of this document is that work. Read it.

---

## 0. Threat model — decide before you spend money

Write down, honestly:

* **Who** must not be able to link you to this server? (curious users; the
  hosting provider; a civil litigant; a national adversary?)
* **What** if the box is seized? Assume full disk access. Nothing on it may
  identify you. Use full‑disk encryption, or keep zero personal data on it and
  treat it as burnable.
* **What** if the `.onion` address leaks to the clear net? It will, eventually.
  The site's *content* and the *server's location* must still not point at you.

If you can't answer these, stop here.

---

## 1. Anonymous infrastructure

### 1.1 Admin workstation

Do **everything** below from a machine/network not linked to you:

* Best: [Whonix](https://www.whonix.org/) in a VM, or [Tails](https://tails.net/).
  Both force all traffic through Tor.
* All access to the server and the provider panel goes **over Tor** (§4).
* One throwaway email, created over Tor, used for nothing else.

### 1.2 Server

* Provider that accepts **Monero** (or freshly‑mixed BTC), allows Tor sign‑up,
  no KYC / ID. Pay from a wallet funded untraceably.
* A tiny VPS is plenty — NNF has no database. 1 vCPU / 1 GB RAM.
* Choose the jurisdiction deliberately. Give the provider only plausible fake
  details, never reused.
* Assume the provider's web console is recorded — never type secrets into it.

### 1.3 Never

* Never point a DNS name you own at the box.
* Never let it make outbound clear‑net connections beyond OS + Docker image
  updates (route even those over Tor if your threat model needs it — §3.3).
* Never administer it from, or mention it on, an identity‑linked account.

---

## 2. Base OS hardening

Minimal Debian 12. Immediately after first boot (via the provider console, then
never again):

```sh
apt update && apt full-upgrade -y

# non-root admin user, key-only
adduser --disabled-password op && usermod -aG sudo op
install -d -m700 -o op -g op /home/op/.ssh
printf '%s\n' 'ssh-ed25519 AAAA... admin' > /home/op/.ssh/authorized_keys
chown op:op /home/op/.ssh/authorized_keys && chmod 600 /home/op/.ssh/authorized_keys
```

`/etc/ssh/sshd_config.d/99-hardening.conf`:

```
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
AuthenticationMethods publickey
AllowUsers op
X11Forwarding no
ListenAddress 127.0.0.1
```

```sh
systemctl restart ssh
```

### 2.1 Firewall — default‑deny, **zero** inbound

Neither the onion service nor SSH‑over‑onion needs an open port — Tor makes only
*outbound* connections.

```sh
apt install -y nftables
```

`/etc/nftables.conf`:

```nft
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
```

```sh
systemctl enable --now nftables
```

> **Docker + firewall.** Docker inserts its own `nat` rules and, for a port
> published to `0.0.0.0`, makes it reachable from the internet **even though the
> host firewall would drop it**. This setup publishes the NNF port to
> **`127.0.0.1` only** (`docker-compose.tor.yml`), which Docker does *not*
> expose externally — the host's Tor is the only thing that connects to it. The
> one rule you must never break: the port binding stays `127.0.0.1:8080:80`,
> never `8080:80`. Verify from outside (§7).

If the provider has an **external** firewall / security group, set it to
deny‑all inbound too. Two layers.

### 2.2 Trim noise & logs

```sh
systemctl disable --now rpcbind avahi-daemon exim4 postfix 2>/dev/null || true
```

* Disable any provider monitoring agent — it phones home and enables
  uptime/traffic correlation.
* `/etc/systemd/journald.conf`: `Storage=volatile`, `RuntimeMaxUse=50M` — journal
  in RAM only, gone on reboot.
* Keep NTP on (a wrong clock breaks onion services and is itself a correlation
  signal); accept the small clear‑net NTP exposure, or route it via Tor later.

### 2.3 Install Docker + Tor on the host

```sh
# official Docker repo — https://docs.docker.com/engine/install/debian/
apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
usermod -aG docker op

# official Tor repo (the distro package lags) — https://support.torproject.org/apt/
apt install -y tor deb.torproject.org-keyring
```

---

## 3. The deployment

`tools/deploy-onion.sh` (Quick start, above) does §3.1 and §3.2 for you. This
section is what it does — and how to do it by hand.

Tor runs **on the host**, exactly like Docker does. NNF runs as the project's
Docker container, publishing its web port to **loopback only**. The host's Tor
forwards that loopback port to the Tor network — that is the *only* way in.

```
                Tor network
                     │
          host tor  (/etc/tor/torrc)
                     │  HiddenServicePort 80 → 127.0.0.1:8080
                     ▼
     127.0.0.1:8080  (loopback, not the public IP)
                     │  docker publishes here only
                     ▼
        NNF container  :80   ──►  ./data   (the whole forum)
```

Push a checkout to the server over Tor (§4), e.g. to `/opt/nnf`.

### 3.1 NNF container — loopback only

The repo ships `docker-compose.tor.yml`: identical to `docker-compose.yml`
except the port is bound to `127.0.0.1`. Read it, then:

```sh
cd /opt/nnf
docker compose -f docker-compose.tor.yml up -d --build
ss -ltnp | grep 8080          # MUST show 127.0.0.1:8080 — never 0.0.0.0
```

`config.php` is seeded from `config.default.php` into `./data` on first start —
edit `./data/config.php` (§5.1), then `docker compose -f docker-compose.tor.yml restart forum`.

NNF makes **no** outbound network connections of its own — no database, no
telemetry, no external assets (verified). Nothing in the container phones home.

### 3.2 Host Tor — the forum onion

`/etc/tor/torrc` (append):

```
# --- NNF forum onion service ---
HiddenServiceDir /var/lib/tor/nnf/
HiddenServicePort 80 127.0.0.1:8080
HiddenServiceVersion 3

# guard-discovery / intro-flood defences
HiddenServiceEnableIntroDoSDefense 1
HiddenServiceEnableIntroDoSRatePerSec 25
HiddenServiceEnableIntroDoSBurstPerSec 200

# Do NOT set HiddenServiceSingleHopMode — it trades the *server's* anonymity
# for latency, the opposite of a location-hidden service.
```

```sh
systemctl restart tor@default
cat /var/lib/tor/nnf/hostname          # your .onion address
```

**Back up `/var/lib/tor/nnf/` encrypted and offline.** That private key *is* the
identity of the forum; anyone who has it can impersonate it.

### 3.3 (Optional) pull Docker images + apt over Tor

So updates don't reveal the box's IP to Docker Hub / Debian mirrors. Tor is
already running; add `SocksPort 127.0.0.1:9050` to `torrc`, then:

* Docker: `/etc/systemd/system/docker.service.d/http-proxy.conf` →
  `Environment="HTTPS_PROXY=socks5h://127.0.0.1:9050"`;
* apt: `Acquire::http::Proxy "socks5h://127.0.0.1:9050";` in
  `/etc/apt/apt.conf.d/`.

Skip only if you accept the registry / mirrors seeing the box's IP.

### 3.4 (Optional) client authorisation — strong

For a closed group, require a client key or the onion won't respond at all
(also shrinks your attack surface to near zero). Per client, in
`/var/lib/tor/nnf/authorized_clients/`:

```
alice.auth   ->   descriptor:x25519:<BASE32-PUBKEY>
```

### 3.5 Changing the onion address later

You might rotate the address (suspected key compromise, a vanity address, or
just starting over). NNF has **no** URL setting — it builds links from the
`Host` header and bakes the *absolute* URL into every `.rss` file it writes,
`index.xml`, `sitemap.xml` and every stored permalink, so the old address is
embedded throughout `./data`.

The repo ships **`tools/rotate-onion.sh`** to do it. Run it on the host, as root,
from inside the checkout:

```sh
sudo tools/rotate-onion.sh                 # fresh random address
sudo tools/rotate-onion.sh ./vanity-dir    # install a key made offline with mkp224o
```

It stops the forum, backs up the old key (as `…​.bak` — shred it once sure),
installs/generates the new key, rewrites every occurrence of the old address in
`./data`, drops the generated indexes, and restarts. It then prints the manual
follow‑ups: update the §5.3 Host filter and rebuild the image, recreate the
§3.4 client‑auth files (tied to the old key), post+delete a test thread to
rebuild `index.xml`/`sitemap.xml`, and announce the new address through a
channel your users already trust (the old one just stops resolving — NNF can't
redirect; for an overlap, add the new service and keep the old
`HiddenServiceDir` running alongside it for a while).

User accounts (`data/users/*.txt`) and `config.php` are **not** tied to the
onion address — the script leaves them. Make a vanity key with
[`mkp224o`](https://github.com/cathugger/mkp224o) on a *separate* machine, never
on the server.

---

## 4. Administer over a second onion service

Set this up **first** — you need it to reach the box at all. It's a second
hidden service in the *same* host `/etc/tor/torrc`, alongside the forum one from
§3.2:

```
# --- SSH admin onion service ---
HiddenServiceDir /var/lib/tor/ssh/
HiddenServicePort 22 127.0.0.1:22
HiddenServiceVersion 3
```

```sh
systemctl restart tor@default
cat /var/lib/tor/ssh/hostname
```

`sshd` already listens on `127.0.0.1` only (§2). From the Whonix/Tails box,
`~/.ssh/config`:

```
Host nnf-admin
    HostName <ssh-onion>.onion
    User op
    ProxyCommand nc -X 5 -x 127.0.0.1:9050 %h %p
```

Now `ssh nnf-admin` works only through Tor. The box has **no** open ports on its
real interface — `nmap` of the IP shows nothing. Add client‑auth to this onion
too (§3.4, same idea under `/var/lib/tor/ssh/`).

---

## 5. NNF configuration

### 5.1 `./data/config.php`

```php
@define('FORUM_HTTPS',   false);   // MANDATORY on an onion: Tor already provides
                                   // encryption + authentication; forcing
                                   // HTTPS/HSTS only breaks access and would
                                   // need a TLS certificate.
@define('FORUM_USERS',   'users'); // stays inside FORUM_DATA, denied by .htaccess
@define('FORUM_NAME',    'Forum'); // nothing identifying
@define('FORUM_SEARCH',  true);    // self-hosted, safe on a Tor-only site
@define('FORUM_TIMEZONE','UTC');   // never your real timezone
```

### 5.2 (Optional) a shared password in front of the forum

Independent of Tor client authorisation (§3.4), NNF can gate the forum — or a
single sub-forum — behind one or more shared passwords in an `access.txt`
(`./data/access.txt` for the whole site, `./data/news/access.txt` for `/news`
only). Any one password lets a visitor in; delete a line to revoke it. Manage
it with `tools/manage-access.sh` (`add` / `list` / `remove`, `-f <sub-forum>`);
see `INSTALL.txt` §2.6. Client authorisation (§3.4) is stronger and also covers
the `.rss` feeds — use that if the group is small and fixed; use `access.txt` if
you need to hand a password to people without provisioning a Tor client key each
time, or to protect just one sub-forum.

### 5.3 Reject non‑`.onion` requests (Host‑header trap)

NNF builds every link — **including the ones written permanently into the
`.rss` files** — from `$_SERVER['HTTP_HOST']`. If anything ever reaches NNF with
a different `Host` (a stray clear‑net probe, a misconfigured proxy, an attacker),
that value gets baked into your data.

The loopback‑only binding already stops clear‑net requests reaching the
container, but add a belt‑and‑suspenders rule. Append to the repo's
`.htaccess.docker` **before** the thread/sub‑forum rules (rebuild the image):

```apache
	# only ever answer to our own onion Host
	RewriteCond %{HTTP_HOST} !^[a-z2-7]{56}\.onion$ [NC]
	RewriteRule ^ - [R=404,L]
```

Then check that `./data/index.xml` and every thread `.rss` contains only the
`.onion` URL.

### 5.4 Scrub identifying strings from the theme

The stock `greyscale` theme loads **no** external fonts, scripts, images or
trackers (verified) — nothing phones out from a rendered page. But it embeds a
few strings identifying the *software* and its author's site. In
`themes/greyscale/*.html` (then rebuild the image):

* delete the `<meta name="msapplication-*">` lines (`starturl` points at
  `forum.camendesign.com`);
* remove the footer `Powered by … NoNonsense Forum` link (or the whole line) if
  you don't want the forum fingerprinted as NNF;
* rewrite `privacy.html` — it names NNF and links out.

Any `themes/greyscale/custom.css` you add: keep every `url(...)` local.

### 5.5 PHP

The image already sets sane defaults, but confirm in a
`docker compose ... exec forum php -i`:

* `expose_php` → Off (add `ENV` / an `.ini` if not);
* `display_errors` → Off, `log_errors` → to `/dev/null` or a volatile path,
  never a persistent file containing paths/IPs.

---

## 6. Make sure the server never confirms "a forum runs here"

The core rule: **there is no clear‑net listener.** The only published port is on
`127.0.0.1`, the host firewall drops all inbound, and SSH is loopback‑only behind
an onion. `nmap` + `curl http://<IP>/` from outside get *connection refused* on
every port — there is nothing to fingerprint. §2.1 + §2.3 + §3.1 + §4 give you
exactly that.

If your provider *forces* some clear‑net service to exist (rare):

* **Isolate it.** Different VM, or at least a different Docker network and no
  shared volumes/logs, so a slip there can't read `/var/lib/tor/nnf` or `./data`.
* **No default‑vhost fallthrough.** Its first/`default_server` vhost returns a
  bare `404`/`444` for unknown hosts and `DocumentRoot`s nowhere near NNF.
* **No status endpoints.** `mod_status`, `mod_info`, `/metrics`, FPM status,
  autoindex — all off. These are the classic ways a probe enumerates apps.
* **No logs, generic errors.** `ErrorLog /dev/null`, `CustomLog /dev/null`,
  `ServerTokens Prod`, `ServerSignature Off`; stock (not NNF‑themed) error
  pages.
* **Timing / traffic correlation.** Keep the box doing *only* this — unrelated
  activity links the forum to that activity, it doesn't hide it. Don't
  restart Tor/containers on a schedule that visibly matches the onion's
  up/down as users see it. The IntroDoS defences (§3.2) and, for a serious
  adversary, [Vanguards](https://github.com/mikeperry-tor/vanguards) raise the
  bar against guard discovery.

Never send an `Onion-Location` header — that advertises an onion *from a
clear‑net site*. You have no clear‑net site, so it must never appear (§7).

---

## 7. Verify — every time you change anything

From **outside** (any clear‑net host, or Tor Browser):

```sh
nmap -Pn -p- <server-ip>                    # expect: every port closed/filtered
curl -m 10 -v http://<server-ip>/           # expect: connection refused
curl -m 10 -v https://<server-ip>/          # expect: connection refused
for h in localhost example.com "$(cat onion-hostname)"; do
  curl -m 10 -s -o /dev/null -w "%{http_code}\n" -H "Host: $h" http://<server-ip>/
done                                        # expect: nothing connects
```

On the **server**:

```sh
ss -ltnp                                    # only 127.0.0.1 entries; nothing on the public IP
docker compose -f docker-compose.tor.yml ps
docker compose -f docker-compose.tor.yml port forum 80   # expect: 127.0.0.1:8080
```

Through **Tor Browser**:

* open the `.onion`, use the forum, post a test thread;
* View Source — no `http(s)://` resource, no clear‑net URL, no analytics/fonts;
* `./data/index.xml` and the thread `.rss` — every link is the `.onion`;
* response headers — **no `Onion-Location`**, `Server:` is generic,
  no `X-Powered-By`.

Optionally run [`onionscan`](https://github.com/s-rah/onionscan) against the
address and fix everything it flags.

---

## 8. Operating it without deanonymising yourself

* **Only ever** reach the forum and the server through Tor, from the anonymous
  workstation. One clear‑net login can be enough.
* Run the forum as a **separate persona** — different writing style, no
  cross‑reference to your real identity, projects, timezone, or local events.
  This applies to `FORUM_NAME`, `about.html`, `mods.txt`, and every moderation
  message.
* Don't post the `.onion` anywhere linked to you. Assume every place you post it
  is logged forever.
* Strip EXIF and re‑encode any image before it goes near the server.
* Patch promptly — `sudo tools/update-onion.sh` for the forum and its image,
  `apt full-upgrade` for the host, all over Tor. An exploited PHP/Apache bug
  bypasses everything above.
* `./data` is the entire forum (no database). Back it up encrypted, over Tor.
* Rehearse abandonment: how you wipe or walk away from the box, and where the
  offline `/var/lib/tor/nnf` backup lives (reachable by you, not by a server seizure,
  not linked to you).

---

## 9. Checklist

- [ ] Server bought with Monero, Tor sign‑up, fake details, no KYC
- [ ] Admin only from Whonix/Tails, only over Tor
- [ ] `nftables` default‑deny inbound + provider firewall deny‑all inbound
- [ ] SSH key‑only, `ListenAddress 127.0.0.1`, reached via its own host onion + client‑auth
- [ ] `nmap`/`curl` from outside → the IP answers on **no** port
- [ ] `docker compose … port forum 80` → `127.0.0.1:8080`, never `0.0.0.0`
- [ ] Host Tor: v3, standard (not single‑hop), IntroDoS defence on
- [ ] `/var/lib/tor/nnf` (onion key) backed up encrypted, offline, unlinked
- [ ] `FORUM_HTTPS = false`; `FORUM_NAME` / timezone non‑identifying
- [ ] `.htaccess.docker` rejects non‑`.onion` Host; `.rss`/`index.xml` links are the onion
- [ ] Theme: `msapplication` / "Powered by" / `privacy.html` scrubbed; no external assets
- [ ] `expose_php = Off`, `display_errors = Off`, logs → `/dev/null`
- [ ] Tor Browser: site works, no clear‑net resource, no `Onion-Location`, generic `Server:`
- [ ] `onionscan` clean
- [ ] Admin persona kept strictly separate; `.onion` never posted under your name
