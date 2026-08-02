# wp-monitor

Monitors a set of websites for unexpected front-page tampering (defacement, injected content, etc.) and
keeps an eye on a mailbox for WordPress plugin/version update notifications, forwarding everything else on.

## What it does

1. A cron job periodically reads a known IMAP mailbox.
2. Emails recognized as WordPress plugin/version update notifications are parsed and recorded; the
   site they mention is auto-registered for monitoring if it isn't known yet.
3. All other emails are forwarded unchanged to a configurable address.
4. A separate cron job fetches every known site's front page — concurrently, capped by
   `SCAN_CONCURRENCY` — and compares the extracted content against a stored "signature", creating one
   if none exists yet. Unreachable sites are flagged and get a report email; recovery sends another.
5. Discrepancies between stored and actual content are compared as a similarity score, tolerant of
   minor/expected changes (dates, view counters, rotating banners, etc.):
   - Similarity below `SIGNATURE_SIMILARITY_THRESHOLD` is flagged as potential tampering — a report
     email is sent and the discrepancy is kept as an alert.
   - Anything else that isn't a byte-for-byte match is accepted as the new signature, but if it's below
     a "notable" cutoff it still sends a lightweight "content updated" report — so a real edit is never
     silently absorbed just because it wasn't big enough to count as tampering.
   - Every check is kept as history regardless of outcome.
6. A Vue3 SPA lists all discovered sites with their current status (incl. unreachable), lets a user
   rename a site or trigger a manual check on demand, and shows per-site alert/outage/snapshot history.
7. A small PHP JSON API (single-admin JWT auth, model/controller layout) backs the SPA and is also
   used by the cron scripts to read/write the database.
8. Storage is a single SQLite file — no database server required.

## Architecture decisions

- **Cron execution**: the *host* crontab triggers the cron scripts (via `docker compose exec`), not a
  cron daemon baked into the image. See [Cron setup](#cron-setup).
- **Mail**: reading the mailbox uses a small hand-rolled IMAP4rev1 client
  (`App\Cron\Imap\ImapClient`/`ImapMessage`, raw TLS socket, no PHP `imap` extension needed) rather
  than a library — see [Why a hand-rolled IMAP client](#why-a-hand-rolled-imap-client) below.
  [`PHPMailer`](https://github.com/PHPMailer/PHPMailer) is still used for forwarding and report emails.
- **Auth**: a single admin account configured via `.env` (username + bcrypt password hash), issuing a
  JWT ([`firebase/php-jwt`](https://github.com/firebase/php-jwt)) on login. No user table/registration.
- **Frontend**: Vue3 + Vite + vue-router + Pinia + Tailwind, built and copied into `htdocs/assets/app`.
- **Database**: SQLite only — no database server/container. The `pdo_sqlite` PHP extension is enabled
  in the `Dockerfile`; there is no `db` service in `compose.yaml`.
- **Scanning**: sites are fetched concurrently (`SiteScanner::fetchMany()`, `curl_multi`, capped by
  `SCAN_CONCURRENCY`) rather than one at a time, so `scan_sites.php`'s runtime doesn't scale linearly
  with the number of monitored sites — the dominant per-site cost is network wait, not CPU, so this is
  a straightforward win over the alternative of splitting the site list across more/batched cron runs.

## Project layout

```
wp-monitor
├── .env                          # docker + app configuration (see below), not committed
├── .env_sample
├── .deploy.env                    # frontend deploy credentials (see below), not committed
├── .deploy.env.sample
├── deploy-frontend.sh              # builds + uploads the SPA (see "Deploying the frontend")
├── compose.yaml                  # webserver only, PHP + sqlite
├── Dockerfile
├── apache-vhost.conf              # AllowOverride for htdocs/.htaccess (front-controller rewrite)
├── xdebug.ini
├── .vscode/                       # PHP Debug (xdebug) launch config
├── logs/                         # cron + app logs (mounted into the container)
├── frontend/                     # Vue3 SPA source (not served directly)
│   ├── index.html
│   ├── vite.config.js
│   ├── tailwind.config.js
│   ├── package.json
│   └── src/
│       ├── main.js
│       ├── App.vue
│       ├── assets/styles/
│       │   └── main.css          # tailwind entrypoint + @font-face (Geomanist / Geologica)
│       ├── router/index.js
│       ├── stores/                # pinia
│       │   ├── auth.js
│       │   └── sites.js
│       ├── api/client.js          # fetch wrapper, attaches JWT bearer token
│       ├── utils/
│       │   └── relativeTime.js    # "last checked" / date formatting helpers
│       ├── components/
│       │   ├── SiteTable.vue        # site list, status colors incl. minor-drift/unreachable highlighting
│       │   ├── SiteStatusBadge.vue
│       │   └── ManualScanPanel.vue
│       └── views/
│           ├── LoginView.vue
│           ├── SiteListView.vue
│           └── SiteDetailView.vue   # rename, alert/outage/snapshot history
└── htdocs/                        # webserver root (`localhost:9080/`)
    ├── index.php                  # front controller: bootstrap + CORS + route dispatch
    ├── .htaccess                  # rewrites all non-file requests to index.php
    ├── composer.json / composer.lock
    ├── vendor/                    # composer deps (gitignored)
    ├── assets/
    │   ├── fonts/
    │   │   ├── geomanist-black-webfont.woff2   # heading font
    │   │   └── geologica/geologica-variable.woff2   # body font
    │   └── app/                   # built SPA output (`vite build`), gitignored
    ├── api/
    │   ├── bootstrap.php          # autoload + .env loading
    │   ├── routes.php             # route table -> controller actions
    │   ├── Core/                  # framework-ish plumbing (not app-specific)
    │   │   ├── Router.php
    │   │   ├── Request.php
    │   │   ├── Response.php
    │   │   ├── Database.php       # PDO SQLite connection (singleton)
    │   │   ├── Migrator.php       # applies database/migrations/*.sql on connect
    │   │   ├── Logger.php         # shared Monolog logger (see "Logging" below)
    │   │   └── Auth/
    │   │       ├── JwtService.php
    │   │       └── AuthMiddleware.php
    │   ├── Controllers/           # JSON API, model/controller layout
    │   │   ├── AuthController.php     # POST /api/login
    │   │   ├── SiteController.php     # GET/POST /api/sites, GET /api/sites/{id}, rename
    │   │   └── ScanController.php     # POST /api/sites/{id}/scan (manual check)
    │   ├── Models/
    │   │   ├── Site.php
    │   │   ├── Snapshot.php
    │   │   ├── Alert.php
    │   │   ├── SiteOutage.php     # open/resolved unreachability episodes
    │   │   ├── PluginUpdate.php
    │   │   └── ProcessedEmail.php
    │   └── Cron/                  # used by both the API (manual scan) and cron/*.php
    │       ├── Imap/
    │       │   ├── ImapClient.php      # raw-socket IMAP4rev1 client (login/search/fetch/store)
    │       │   └── ImapMessage.php     # header/MIME parsing, safe charset decoding
    │       ├── MailReader.php         # uses Imap\ImapClient to list/fetch/mark-seen messages
    │       ├── PluginUpdateParser.php # recognizes/parses WP plugin update mails
    │       ├── Mailer.php              # shared PHPMailer/SMTP setup
    │       ├── MailForwarder.php      # forwards non-plugin-update mail
    │       ├── ReportMailer.php       # sends tamper/outage/recovery/drift report emails
    │       ├── OutageTracker.php      # opens/resolves site_outages, avoids duplicate alerts
    │       ├── SiteScanner.php        # concurrent fetch (curl_multi) + extracts relevant content
    │       └── SignatureComparer.php  # normalizes/diffs content vs stored signature
    ├── cron/                      # entry points invoked by the host crontab
    │   ├── check_mail.php
    │   ├── scan_sites.php
    │   └── backfill_sites_from_plugin_updates.php   # one-off: seed sites from existing plugin_updates rows
    └── database/
        ├── wp-monitor.sqlite      # SQLite database file (gitignored)
        └── migrations/
            ├── 001_create_sites.sql
            ├── 002_create_snapshots.sql
            ├── 003_create_alerts.sql
            ├── 004_create_plugin_updates.sql
            ├── 005_create_processed_emails.sql
            ├── 006_add_status_to_plugin_updates.sql
            └── 007_create_site_outages.sql
```

### Data model (SQLite)

Table | Purpose
-|-
`sites` | Monitored site: url, name, current status (`ok` / `tampered` / `unreachable` / `unknown`), timestamps.
`snapshots` | Every scan's normalized content + hash for a site — the signature history.
`alerts` | A flagged discrepancy: which snapshot triggered it, diff summary, open/resolved, timestamps.
`site_outages` | An unreachability episode for a site: error message, detected/resolved timestamps.
`plugin_updates` | Parsed WP plugin/version update notifications extracted from mail, incl. success/failed status.
`processed_emails` | IMAP UID/Message-ID of already-handled mails, so `check_mail.php` is idempotent across runs.

## Configuration (`.env`)

Copy `.env_sample` to `.env` and fill in the values — `.env` is gitignored, `.env_sample` is not. In
addition to the existing `NETWORK_NAME` / `PROJECT_NAME` variables, the app needs:

```
# IMAP mailbox to monitor
# port 993 = implicit TLS (encryption=ssl, the default) — most providers.
# port 143 = STARTTLS (encryption=tls) or plaintext. Never 465 — that's SMTPS, not IMAP.
IMAP_HOST=
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_USER=
IMAP_PASSWORD=
IMAP_MAILBOX=INBOX

# outgoing mail (forwarding + tamper reports)
# port 587 = STARTTLS (encryption=tls, the default). port 465 = implicit TLS (encryption=ssl).
# These must match — forcing the wrong mode for the port is a silent-failure-prone mismatch.
SMTP_HOST=
SMTP_PORT=587
SMTP_TIMEOUT=15
SMTP_ENCRYPTION=tls
SMTP_USER=
SMTP_PASSWORD=
FORWARD_TO_EMAIL=          # non-plugin-update mail is forwarded here
REPORT_TO_EMAIL=           # tamper/outage/recovery/drift report emails are sent here

# auth
JWT_SECRET=                # >= 32 random bytes, e.g. `openssl rand -base64 32` (firebase/php-jwt rejects shorter keys)
ADMIN_USERNAME=
ADMIN_PASSWORD_HASH=       # bcrypt hash, e.g. `php -r 'echo password_hash("x", PASSWORD_BCRYPT);'`

# sqlite
DB_PATH=/var/www/html/database/wp-monitor.sqlite

# logging (see "Logging" section below)
LOG_PATH=
LOG_LEVEL=info
LOG_ROTATE_CRON=0 0 * * *
LOG_ROTATE_MAX_FILES=14
LOG_ROTATE_MIN_SIZE=0
LOG_ROTATE_COMPRESS=false

# cron safety limits
CRON_MEMORY_LIMIT=512M
IMAP_MAX_MESSAGE_SIZE=20971520

# scanner settings
SITE_SCANNER_TIMEOUT=10           # per-request curl timeout, seconds
SCAN_CONCURRENCY=10               # sites fetched at once by scan_sites.php (curl_multi)
SIGNATURE_SIMILARITY_THRESHOLD=0.97   # below this similarity, a change is flagged as tampering
```

> \[!IMPORTANT]
> Our `.env` loader (`htdocs/api/bootstrap.php`) is intentionally minimal: it splits each line on the
> first `=` and trims whitespace — it does **not** strip surrounding quotes. Never wrap values in `"`
> or `'`; write `IMAP_PASSWORD=my#pass` and `LOG_ROTATE_CRON=0 0 * * *`, not `IMAP_PASSWORD="my#pass"`.
> A `#` or space inside a value is fine as-is; quotes become part of the value and will silently break
> whatever reads it (wrong password, malformed cron expression, etc.).

## Logging

Errors and key events are logged via [`monolog/monolog`](https://github.com/Seldaek/monolog) using
[`kingsoft/monolog-handler`](https://github.com/theking2/kingsoft-monolog-handler)'s
`CronRotatingFileHandler`, which rotates the log file on a cron schedule (independent of the app's own
mail/scan cron jobs) rather than by process restart. `App\Core\Logger::get()` returns a shared
`Monolog\Logger` instance; call it wherever an event is worth recording (`debug` for verbose
tracing, `info` for normal operation, `warning` for recoverable/notable events like a failed login or
detected tampering, `error` for exceptions/failures).

- `LOG_PATH` — defaults to a `logs/` folder one level above `htdocs` (i.e. the same `logs/` already
  used for xdebug output in docker-compose, or the Plesk vhost-root `logs/` folder — create it if it
  doesn't exist and make sure the PHP process can write to it).
- `LOG_LEVEL` — minimum level written: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`.
- `LOG_ROTATE_CRON` — a standard 5-field cron expression for when the log file itself gets rotated
  (checked lazily on each write, not by a separate system cron entry).
- `LOG_ROTATE_MAX_FILES` / `LOG_ROTATE_MIN_SIZE` / `LOG_ROTATE_COMPRESS` — rotation retention/size/compression.
- If the configured log file can't be opened (bad `LOG_PATH`, permissions), `Logger::get()` falls back
  to `stderr` rather than throwing — logging failing must never be *why* a real error goes unlogged.

`index.php` also wraps the router dispatch in a try/catch: any uncaught exception is logged (with full
stack trace) and converted into a generic `500` JSON response, so a bug never leaks a stack trace (with
server file paths) to the client regardless of the server's `display_errors` setting.

### Why a hand-rolled IMAP client

Reading the mailbox originally used [`webklex/php-imap`](https://github.com/Webklex/php-imap). It was
dropped for two concrete reasons hit in production: it pulled in Laravel's `illuminate/*` and Symfony
components just for basic collections (which is what forced a PHP 8.4 floor), and its body-decoding
path calls `iconv($from, $to.'//IGNORE', $str)` — a combination with a known glibc bug that can exhaust
PHP's memory limit converting even a *tiny* string when a message's declared charset doesn't match its
actual bytes, completely independent of message size. `App\Cron\Imap\ImapClient`/`ImapMessage` replace
it: a raw-socket IMAP4rev1 client covering only what this app needs (login, select, search unseen,
fetch header/size, fetch full message, mark seen), with our own MIME/charset handling using
`mb_convert_encoding` instead of the buggy `iconv` form. Net effect: `htdocs/vendor` dropped from ~20
packages to 6, and the PHP floor dropped back to 8.1.

### Cron safety limits

Because the IMAP client fetches headers/size only first (`MailReader::fetchUnseen()`), `check_mail.php`
can decide *before* decoding anything: any message over `IMAP_MAX_MESSAGE_SIZE` bytes is skipped
without ever fetching its body (marked handled, classification `skipped_too_large`), and only messages
under that threshold get their full body fetched (`MailReader::fetchBody()`). `CRON_MEMORY_LIMIT` raises
PHP's memory limit for both cron scripts as an additional line of defense. A single message's
processing failure (a bad forward, an unusual encoding, ...) is caught and logged without aborting the
rest of the batch, and is deliberately left unseen so it's retried next run — except for the oversized
case above, which is marked seen immediately (before any risky decode is attempted) precisely so a
genuinely poisonous message can't crash the same run forever instead of just being skipped once.

## Cron setup

The host's crontab calls into the running container via `docker compose exec`. Example (every 10
minutes), from the project root:

```cron
*/10 * * * * cd /path/to/wp-monitor && docker compose exec -T server php /var/www/html/cron/check_mail.php >> logs/cron-mail.log 2>&1
*/15 * * * * cd /path/to/wp-monitor && docker compose exec -T server php /var/www/html/cron/scan_sites.php >> logs/cron-scan.log 2>&1
```

## Frontend dev workflow

```
cd frontend
npm install
npm run dev        # local dev server against the API
npm run build      # outputs to ../htdocs/assets/app
```

## Deploying the frontend

`deploy-frontend.sh` builds the SPA and uploads `htdocs/assets/app` to the server via `lftp`
(`ftp`, `ftps`, or `sftp`). One-time setup: copy `.deploy.env.sample` to `.deploy.env` and fill in
your host/credentials — same `.env`/`.env_sample` pattern as the app config, and `.deploy.env` is
gitignored for the same reason.

```
./deploy-frontend.sh          # prompts for confirmation before uploading
./deploy-frontend.sh --yes    # skips the prompt, for scripting/CI
```

It uses `mirror --reverse --delete`, so anything on the remote side no longer present locally gets
removed — necessary because Vite hashes bundle filenames on every build, otherwise old JS/CSS
files pile up forever. This is exactly why `DEPLOY_REMOTE_PATH` must point specifically at the
built-app folder (e.g. `/httpdocs/assets/app`) and never at the whole webroot. Leave
`DEPLOY_PASSWORD` blank with `DEPLOY_PROTOCOL=sftp` to use SSH key/agent auth instead of a password.

## Fonts

- Headings: Geomanist (`htdocs/assets/fonts/geomanist-black-webfont.woff2`).
- Body text: Geologica (`htdocs/assets/fonts/geologica/geologica-variable.woff2`).

---

## LAMP + xdebug base setup

This project was bootstrapped from a generic docker LAMP + xdebug template. The following still
applies to the container/webserver/debugger scaffolding itself.

> \[!NOTE]
> A configured `xdebug.ini` file sits in the project root. It defaults to allow step debugging. After
> changing make sure to restart the webserver!

### Setup docker containers

#### With Containers VSCode extension

1) Install VScode extension "Container Tools"
2) Update `.env` file (setting network and project name, plus the app configuration above)
3) Open `compose.yaml` file and click "Run All Services"

After that the following service runs with this uri:

URI|Service
-|-
localhost:9080/ | webroot, contents of folder = `./htdocs`

#### Without the extension

In a terminal, start the application by running: `docker compose up --build`.

### Setup php xdebug

* Install xdebug extension
* The `.vscode` folder contains the setup for the VSCode PHP debugger (`launch.json`), mapping the
  container's `/var/www/html` to `${workspaceFolder}/htdocs`.
