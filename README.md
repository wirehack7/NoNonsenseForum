# NoNonsense Forum

A simple forum that focuses on discussion and simplicity.

> © Copyright (CC-BY) Kroc Camen, 2010–2015 · <http://camendesign.com/nononsense_forum>
> See [`LICENCE.txt`](LICENCE.txt) for licence details.

## How NoNonsense differs from other forums

- **No database.** Threads are just RSS feeds. When a reply is added, a new item is added to the feed.
- **No hoops to jump through.** No registration, no e-mail confirmation, no CAPTCHA. Type your message, give a name and password you want to use, and your post is made. Use the same name / password pair in the future to keep the same name — that's it.
- **No clutter.** No user profiles, no "status updates", no signatures, no user ranks. Just discussion.

## Quick start (Docker)

With Docker installed you can run NNF without installing PHP or Apache. From the root of a checkout:

```sh
docker compose up -d
```

Then visit <http://localhost:8080/>.

Everything the forum writes — sub-forums, threads, `config.php`, the `users` folder — lives in `./data/` in your checkout, directly editable and backup-able on the host. `config.php` is seeded from `config.default.php` on first start. See [`INSTALL.txt`](INSTALL.txt) §1.7 for details.

To run it **hidden-only as a Tor onion service**, see [`TOR.md`](TOR.md).

For a normal (non-Docker) install, or to customise the forum, see [`INSTALL.txt`](INSTALL.txt).

## Where the admin files live

NNF has no admin panel — you administer it by creating small text files next to the threads (and by signing in and clicking buttons). Every file below goes in the forum's data directory, which is:

- **Docker:** `./data/` in your checkout (e.g. `./data/mods.txt`, `./data/news/mods.txt`). Edit on the host, or `docker compose exec forum vi /var/www/html/mods.txt`. Files are re-read on every request — no restart needed.
- **Plain install:** the NNF folder itself (or a sub-folder for a sub-forum).

## Admin & moderators

### Set up

Provide a username for the admin and any moderators:

- Create a **`mods.txt`** file in the forum (or a sub-forum).
- The name on the **first line is the forum's admin**.
- Add each moderator's name on its own line:

  ```
  Kroc
  theraje
  SpeedoJoe
  ```

- Mods in the **root** `mods.txt` can moderate everywhere, including locked sub-forums.
- Mods in a **sub-forum** `mods.txt` (e.g. `news/mods.txt`) can only moderate that sub-forum.

> **Claim the names immediately.** Make each admin/mod account real by signing in and posting once — otherwise anybody could take the name and take control. To "register" a name you just sign in with it and a password of your choice the first time; the password hash is then stored in `users/`.

### Sign-in

- Click **"sign in"** at the bottom of the page and enter your name / password.
- Threads can be locked/unlocked and stuck/unstuck with the links at the bottom of the page.
- To sign out you must **quit your browser fully** (not just close the tab) or clear its cache.
- Due to a flaw in HTTP authentication, names with accented / Unicode letters can't sign in — moderators and members must use basic letters, numbers and punctuation.

## Things to note

### Post appending

Because threads are RSS feeds, the text-to-HTML conversion at post time is one-way — you can't edit posts (short of editing the RSS file by hand). Instead, text can be **appended** to the end of a post.

- Users can append to their own posts.
- Moderators can append to any post.

### Post deleting

To avoid abuse, users cannot permanently delete their own posts.

- When a user deletes their post, the text is replaced with a message like *"This post was deleted by its owner"* (or *"a moderator"*); the name and time remain.
- A moderator can delete any post, likewise.
- A blanked-out deleted post can be removed **permanently** by a moderator deleting it again — but only if it's on the last page of replies, so permalinks aren't broken by shifting page boundaries.

## Sub-forums

Sub-forums are **simply folders**.

- The folder name is the sub-forum name. It can contain any characters your server's OS allows except `.`, `<`, `>` and `&`.
- The folder must be writable by the web server.
- Nested sub-forums are supported to any reasonable depth (e.g. `Music/Techno/`).

So on Docker: `mkdir ./data/news` makes a `/news` sub-forum. Drop a `mods.txt` / `locked.txt` / `about.html` in there to configure it.

## Lock & sticky threads

Locked threads can't be replied to; sticky threads stay at the top of a forum regardless of page.

- Sign in as the **admin**.
- Click the **Un/Lock** or **Un/Sticky** button on the thread.

## Forum locking

The root forum and each sub-forum can be individually restricted. Create a **`locked.txt`** containing one word:

| `locked.txt` | Effect |
| --- | --- |
| `threads` | Only moderators/members can start new threads; anybody can reply. |
| `news` | As `threads`, but the forum is ordered by original posting date (descending), not last reply. |
| `posts` | Only moderators/members can start threads **or** reply. Anybody can read. |

### Members

Members can post in a locked forum but have no moderator powers.

- Create a **`members.txt`** and add one name per line.
- Members of the root forum are **not** automatically members of sub-forums (unlike mods).
- Members must sign in to post in locked forums.

## Private forums

NNF has two ways to keep people out:

1. **`access.txt` — a shared door password (built in).** Put one or more passwords (one per line) in `users/access.txt` and the whole site sits behind a password prompt. Any one password grants access; delete a line to revoke it. Lines may be plain text or a `password_hash()` string. See [`INSTALL.txt`](INSTALL.txt) §2.6. This does **not** cover direct requests for the static `.rss` / `.xml` feed files (the web server serves those without running PHP).

2. **`.htpasswd` — web-server auth (covers the feeds too).** Protect the relevant directory with HTTP Basic auth at the Apache level. The users in `.htpasswd` must have the same password as their NNF username. Setting this up needs some `.htaccess` knowledge and isn't covered here.

For a Tor onion service, **onion client authorisation** (see [`TOR.md`](TOR.md) §3.4) gates the entire service, feeds included.

## Are the `.txt` files web-readable?

- `mods.txt`, `members.txt`, `locked.txt`, `sticky.txt` and `about.html` **are** served directly (e.g. `/mods.txt`) — but they contain nothing secret (the mod/member names are already shown in the page footer).
- `config.php` is executed, not served, so its contents aren't exposed.
- `users/` — the password hashes, the anti-spam secret and `access.txt` — is blocked by `.htaccess`. If you run **without** `.htaccess`, you must relocate the `users` folder (NNF tells you how); prefer `password_hash()` values in `access.txt` in that case.

## Acknowledgements

Thanks to the users of [Camen Design Forum](http://forum.camendesign.com) for testing and support, and to everyone who suggested ideas or contributed directly.

<details>
<summary>Contributors</summary>

| Name | Contributions |
| --- | --- |
| 1seann | Discovering path error in `sitemap.xml` |
| bh8(dot)vn & zuchto | Improve transliteration further; fallback if `iconv` is missing |
| Bruno Héridet | Duplicate ID in the HTML; major DOMTemplate bug munging querystrings |
| David Hund | Code typo in `DOMDocument`; major DOMTemplate bug munging querystrings |
| folderol | Reporting of Apache "NOYB" identifier |
| fyra | IDN URLs; UTF-8 characters no longer hex-encoded in the output |
| gardener | Critical typo in `lang.example.php` |
| JBark | Use `clearstatcache` to ensure index ordering; accidental double `<link>` to favicon |
| JJ | Wrong usage of PHP header function; `noindex, nofollow` on delete page; blockquote syntax idea |
| Jon Gjengset / Jonhoo | Original "Grayscale" theme; original mobile theme; `$` alternative syntax for code blocks; read-locking of threads during writes; HTTPS support; PHP short tags issue; delete message consistency; many HTML & CSS fixes |
| Jose Pedro Arvela / jparvela | `static::` → `self::`; `@user` syntax suggestion |
| macsupport.gr | Regex backtrace limit |
| Martijn / Zegnat | Lynx support; `rel="nofollow external"` on external links; `.htaccess` compatibility with macOS; title-line self links; duplicate appends; transliteration; whitespace trimming; missing `?` in no-HTACCESS URLs; constant support for UTF-8 handling; major DOMTemplate querystring bug |
| nkrs | Opera speed dial help |
| Nicolai | Unnecessary ChromeFrame header in `.htaccess` |
| Nikolai | `static::` → `self::`; Opera speed dial help |
| oldtimes | Original suggestion to transliterate thread titles |
| Paul M | Lock button sometimes showing by accident |
| Philip Butkiewicz | Fix `<script>` output in DOMTemplate |
| Richard van Velzen / rvanvelzen | Running in a sub-folder; HTTPS support; remove `/users/` from `robots.txt`; CSS fixes; inline code / heading / divider markup; new-thread fault; subdomain-with-dash URL parsing; `$1` stripped from code spans; error-message wording; closing bracket in quoted URLs; blockquote regex; post starting with a code block |
| Sani | Better tag matching when repairing HTML; stickies not showing with no other threads; leading `0` in `Expires` header; DOMTemplate speed; HiDPI graphics suggestion |
| starbeamrainbowlab | Discovering missing `?` in no-HTACCESS URLs |
| Stephen Taylor | Appends double-encoding HTML; `@name` not working with no `.htaccess` |
| Steve Bir | Pages not working in sub-forums |
| TCB | iOS testing for the rotation / zooming bug |
| Temukki | Delete page missing; timezone option |

Anybody forgotten along the way — get in touch.

</details>
