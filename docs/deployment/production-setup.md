# Production deployment checklist

How the reference service at ric.theahg.co.za is set up, and what to check after any change to it. The values are the real ones for this host; another deployment substitutes its own. Secrets live only in `.env` and never in the repository.

## 1. Environment (`.env`)

`.env` is owned by `root:www-data` with mode 640, so the php-fpm worker can read it and other users cannot.

| Setting | Production value | Why |
|---|---|---|
| `APP_ENV` | `production` | Turns on the HTTPS redirect in `SecurityHeadersMiddleware` |
| `APP_DEBUG` | `false` | With `true`, a failing request shows a stack trace with file paths and configuration values |
| `LOG_LEVEL` | `info` | `debug` fills `storage/logs` with query detail |
| `APP_URL` | `https://ric.theahg.co.za` | Used to build absolute IRIs |
| `OPENRIC_OPEN_WRITE` | `false` | Anonymous writes stay off; the sandbox purge handles any that were made while it was on |

: Environment settings for production

Secrets kept in `.env` and nowhere else: `APP_KEY`, `DB_PASSWORD`, `FUSEKI_USER` and `FUSEKI_PASSWORD`, `OPENRIC_AI_KEY`, `OPENRIC_STATS_TOKEN`. Read them with `grep`, never print them.

The configuration is not cached (`bootstrap/cache` holds only `packages.php` and `services.php`), so an edit to `.env` takes effect on the next request. If anyone ever runs `php artisan config:cache`, every later `.env` edit needs it run again.

Changing `APP_ENV` does not affect artisan: `bin/release` runs no artisan commands, and none of the `openric:` commands asks for confirmation in production.

## 2. Web server (nginx)

The site file is `/etc/nginx/sites-enabled/ric.theahg.co.za.conf`; the copy in `deploy/ric.theahg.co.za.conf` is the reference.

- Port 80 answers every request with a 301 to HTTPS.
- TLS uses the Let's Encrypt certificate for theahg.co.za, renewed by Certbot.
- nginx passes `HTTPS=on` to php-fpm through `fastcgi_params`, so Laravel sees requests as secure. No proxy sits in between, so `X-Forwarded-Proto` and trusted proxies are not needed.
- nginx sets `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options`, `Referrer-Policy` and the Content Security Policy. The middleware sets only `Strict-Transport-Security` and `Permissions-Policy`. Setting a header in both places sends it twice; that happened once already.

## 3. php-fpm and file permissions

php-fpm runs with `ProtectSystem=full`, which makes `/usr` read-only for the worker. The drop-in `/etc/systemd/system/php8.3-fpm.service.d/openric-storage.conf` grants write access back:

```ini
[Service]
ReadWritePaths=/usr/share/nginx/OpenRiC/storage
ReadWritePaths=/usr/share/nginx/OpenRiC/bootstrap/cache
```

`storage/logs` carries a default ACL for `www-data`. Do not run `php artisan` as root in this directory: a log file created by root blocks the worker until it is chowned. Use `sudo -u www-data php artisan ...`.

## 4. Code that runs

The service code in `packages/ahg-ric` is mirrored into `vendor/ahg/ric`, and only the vendor copy runs. A release copies each changed file with `install -m 664` and compares the two with `cmp`. Files under `app/`, `config/` and the root `routes/` are live as soon as they are saved.

## 5. Scheduler

`/etc/cron.d/openric-schedule` runs `schedule:run` every minute as `www-data`, under `flock`, logging to `/var/log/openric-schedule.log`. It is installed from `deploy/cron/openric-schedule`.

| Command | When (SAST) | What it does |
|---|---|---|
| `openric:rebuild-graph` | 03:00 daily | Rebuilds the public SPARQL graph from the API serializer. It refuses the build if a `rico:` term is not in RiC-O 1.1, an `openricx:` term is not in the published extension ontology, or the size moves outside half to double the last build. |
| `openric:daily-digest` | 06:30 daily | Sends the daily digest |

: Scheduled commands

## 6. Fuseki

Fuseki listens on `localhost:3030` only, with basic authentication from `.env`. Datasets: `openric` (live), `openric_staging` (disposable, for tests), `openric-model` and `ric`. The rebuild keeps seven N-Triples backups of the live graph in `storage/app/openric-graph/openric/`.

## 7. API keys

Mint keys with `openric:issue-key` (or `ric:mint-service-key` for the Heratio service key) and switch them off with `openric:revoke-key`. A raw key is shown once, at minting. Delete routes need the `delete` scope, not `write`.

## 8. Checks after a deployment

```
curl -sI http://ric.theahg.co.za/ | head -1                                   # 301
curl -sI https://ric.theahg.co.za/api/ric/v1/ | grep -ci x-frame-options        # 1
curl -s https://ric.theahg.co.za/api/ric/v1/ | jq -c '{version, spec: .openric_conformance.spec_version}'
curl -s -o /dev/null -w '%{http_code}\n' -X DELETE https://ric.theahg.co.za/api/ric/v1/places/1   # 401
systemctl show php8.3-fpm | grep ReadWritePaths                                # includes OpenRiC
/usr/share/nginx/openric-spec/conformance/probe.sh                             # read-only conformance probe
```

The probe only reads. Probes or tests that write go to `openric_staging`, never to the live dataset.
