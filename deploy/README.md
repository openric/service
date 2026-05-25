# OpenRiC Service — deployment notes

## What this is

A standalone Laravel 12 app that serves `/api/ric/v1/*`. It reuses Heratio's
`ahg-ric`, `ahg-api`, and `ahg-core` packages via a composer `path` repo that
points at `/usr/share/nginx/heratio/packages/*`. No code duplication — every
fix pushed to Heratio's packages is picked up here on the next request.

During Phase 4.3 (current), this app **shares Heratio's MySQL database** —
the `ric_*` tables, `relation`, `object`, `slug`, `ahg_dropdown`. Heratio and
this service are both read/write clients. Phase 4.3 Option B moves RiC tables
to a separate DB.

## Local test

```bash
cd /usr/share/nginx/OpenRiC
php artisan serve --port=8100 --host=127.0.0.1
# in another shell:
curl http://127.0.0.1:8100/api/ric/v1/health
# {"status":"ok","service":"RIC-O Linked Data API","version":"1.0"}
```

## Production wiring — already provisioned

The host already has the vhost file at
`/etc/nginx/sites-available/ric.theahg.co.za.conf`. The current version serves
the Flask community site at `/` and was waiting for a Laravel app to land at
`/app`. We don't use the `/app` prefix — instead this service serves
`/api/ric/v1/*` directly.

### One-shot apply

```bash
sudo cp /usr/share/nginx/OpenRiC/deploy/ric.theahg.co.za.conf /etc/nginx/sites-available/
sudo nginx -t && sudo systemctl reload nginx
```

The new vhost:

- Keeps the Flask community site (`/`, `/docs`, `/whats-new`, `/explorer`,
  `/login`, `/register`, `/logout`, `/ric-api`, `/static`) proxying to port 5055.
- Serves the Laravel API at `/api/ric/v1/*` (longest-prefix match wins, so
  these never conflict).
- Serves the spec-canonical semantic URI resolver at `/id/{kind}/{id}`
  (openric-spec viewing-api §3.1). Content-negotiates `Accept` and 303s
  to either the JSON-LD API endpoint or the human-readable view. The
  `location ~ ^/id(/|$)` block must stay in the vhost — without it
  nginx returns its own 404 before Laravel sees the request.
- 301-redirects old `/sparql` and `/oai` → `/api/ric/v1/sparql` and
  `/api/ric/v1/oai` for back-compat.
- Drops the dead `/app/*` + `@laravel` blocks that referenced a Laravel app
  that was moved into Heratio.

### Verify

```bash
curl -sI https://ric.theahg.co.za/api/ric/v1/health
# HTTP/2 200
# content-type: application/json
```

## Mint the service API key (for Heratio → this service auth)

After the vhost is live, run on the server:

```bash
cd /usr/share/nginx/OpenRiC
# Pick an owner user id (any admin):
mysql heratio -sNe "SELECT id,username FROM user WHERE username='johanpiet'"
# e.g. 900148

php artisan ric:mint-service-key --owner=900148 --name="heratio → openric-service"
# Output:
#   Key minted (row id=NNN, prefix=xxxxxxxx, scopes=read,write,delete).
#   Copy the following into Heratio's .env:
#     RIC_SERVICE_API_KEY=<64-char hex>
#   This is the LAST time the raw key is shown.
```

## Flip Heratio to use the service

Edit Heratio's `.env`:

```
RIC_API_URL=https://ric.theahg.co.za/api/ric/v1
RIC_SERVICE_API_KEY=<paste from the mint command>
RIC_HTTP_TIMEOUT=5
```

Reload config:

```bash
cd /usr/share/nginx/heratio
php artisan config:clear
php artisan ric:verify-split  # should return 15 pass, 0 fail
```

## Rollback

Blank `RIC_API_URL` in Heratio's `.env`. Heratio reverts to in-process RiC.
The service at `ric.theahg.co.za/api/ric/v1/*` keeps running harmlessly
(shared DB — no data loss).
