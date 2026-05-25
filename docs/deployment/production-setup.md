# Production deployment: secure defaults and .env guidance

This document summarises recommended production configuration values and deployment notes for OpenRiC. Do NOT commit real secret values into the repository. Use a secrets manager or your deployment pipeline to inject runtime values.

## Recommended .env (production) - guidance only
- APP_ENV=production
- APP_KEY= (set via `php artisan key:generate` on the deployment host)
- APP_DEBUG=false
- APP_URL=https://your-production-domain.example
- LOG_CHANNEL=stack
- LOG_LEVEL=info

FUSEKI_URL=http://localhost:3030
FUSEKI_USER= (set only if Fuseki requires auth)
FUSEKI_PASSWORD= (set only if Fuseki requires auth)

Notes:
- Keep APP_KEY secret. Do not commit it to version control.
- APP_DEBUG must be false when APP_ENV=production to avoid leaking stack traces or secrets.
- Use environment-specific secret injection (Vault, GitHub Actions secrets, Docker secrets, cloud provider secret manager).

## Server / webserver recommendations
- Terminate TLS at the edge (load balancer or reverse proxy) and forward to the app over localhost or an internal network.
- Configure the webserver to redirect HTTP -> HTTPS. The application also includes a middleware that will redirect to HTTPS when `APP_ENV=production`.
- Set appropriate HSTS headers (the middleware added sets a long max-age by default).

Example Nginx redirect snippet:

server {
    listen 80;
    server_name your-production-domain.example;
    return 301 https://$host$request_uri;
}

## Application hardening notes
- The repository now includes a SecurityHeaders middleware that sets:
  - Strict-Transport-Security
  - X-Frame-Options (DENY)
  - X-Content-Type-Options (nosniff)
  - Referrer-Policy
  - Permissions-Policy

- Verify `APP_DEBUG=false` in production. Consider adding a startup check in your deployment pipeline to warn if APP_DEBUG is set to true.

## Fuseki / SPARQL backend
- If your Fuseki instance is exposed only internally, set FUSEKI_URL to the internal address. If Fuseki requires authentication, inject FUSEKI_USER and FUSEKI_PASSWORD from your secret store.
- Do not store credentials in the repo; use environment variables or a secret manager.

## Deployment checklist (recommended)
1. Ensure environment secrets are set (APP_KEY, FUSEKI credentials if needed).
2. Ensure APP_ENV=production and APP_DEBUG=false before exposing the service.
3. Configure TLS (obtain certs, configure load balancer or nginx).
4. Ensure reverse-proxy forwards X-Forwarded-Proto for the app to detect secure requests.
5. Monitor logs (LOG_LEVEL=info) and configure alerting for application errors.

## Runbook snippet - verifying production safety
- On deployment host run:
  - echo $APP_ENV
  - php artisan key:generate --show  (to confirm APP_KEY generation; do NOT commit the output)
  - curl -I -H "Host: your-production-domain.example" http://127.0.0.1/  (expect redirect to https)

## Further reading / links
- Do not commit secrets: use your cloud provider or Vault. 
- Consider adding runtime environment validation in `AppServiceProvider` to fail-fast when required env vars are missing.


---
This file was created by the AHG Workbench Agent as part of issue #4 (production hardening).
