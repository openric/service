# OpenRiC Service — deployment notes

## What this is

A standalone Laravel 12 app that serves `/api/ric/v1/*` — the same endpoints Heratio exposes from its `ahg-ric` package, but extracted into a separate deployable.

During Phase 4.3 (current), this app shares Heratio's MySQL database. The `ric_*` tables, the `relation` table, and the `object`/`slug`/`ahg_dropdown` helper tables live in the same place as Heratio's. Heratio and this service are both read/write clients.

## Quick local test

```bash
cd /usr/share/nginx/openric-service
php artisan serve --port=8100 --host=127.0.0.1
# in another shell:
curl http://127.0.0.1:8100/api/ric/v1/health
# {"status":"ok","service":"RIC-O Linked Data API","version":"1.0"}
curl 'http://127.0.0.1:8100/api/ric/v1/autocomplete?q=egypt&limit=3'
```

## Production deployment

See `ric.theahg.co.za.conf` in this directory — drop into `/etc/nginx/sites-available/`, symlink into `sites-enabled/`, reload nginx.

DNS: add `ric.theahg.co.za` as A/AAAA or CNAME to the same host running Heratio. Both apps share the same PHP-FPM pool (`php8.3-fpm.sock`) — no extra pool needed because they're cheap and low-traffic.

TLS: the existing `theahg.co.za` Let's Encrypt cert may already cover `ric.theahg.co.za` as a SAN. If not:

```bash
certbot --nginx --expand -d ric.theahg.co.za
```

## Mint the service API key (for Heratio → this service auth)

After the nginx vhost is live, run this on the server to create a service-level key with `read,write,delete` scope:

```bash
cd /usr/share/nginx/openric-service
php artisan tinker
```

Then inside tinker:

```php
$key = bin2hex(random_bytes(32));
\DB::table('ahg_api_key')->insert([
    'name' => 'heratio → openric-service',
    'api_key' => hash('sha256', $key),
    'scopes' => json_encode(['read', 'write', 'delete']),
    'is_active' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "RIC_SERVICE_API_KEY=$key\n";
```

Copy the printed `RIC_SERVICE_API_KEY=…` line into Heratio's `.env`, then:

```
RIC_API_URL=https://ric.theahg.co.za/api/ric/v1
RIC_SERVICE_API_KEY=<paste>
```

Reload Heratio (`php artisan config:clear`). The `RicEntityController::callRicApi()` helper detects the external URL and switches from cookie-forwarding to `X-API-Key` auth automatically.

## Rollback

Set `RIC_API_URL=` (blank) in Heratio's `.env` — Heratio reverts to its own in-process RiC module. The two services coexist safely; flipping between them is free.
