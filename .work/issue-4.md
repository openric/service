Problem

Production configuration is not hardened: APP_ENV=local, APP_DEBUG=true, HTTPS redirect and security headers missing, and Fuseki credentials are not sourced from .env.

Tasks

- Set APP_ENV=production and APP_DEBUG=false in deployment env.
- Add HTTPS redirect and security headers in nginx config under deploy/.
- Move Fuseki credentials into .env and remove any hard-coded secrets.
- Add a checklist to docs/deployment.md for production hardening.

Acceptance criteria

- APP_ENV and APP_DEBUG set in production.
- HTTPS enforced and security headers present.
- No hard-coded credentials in code or deploy scripts.

Estimated time: 1–2 hours.