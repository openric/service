Problem

There is no dedicated API router file; API routes live on the web router. This causes inconsistent middleware (CORS, throttle) and may expose web-only middleware to API consumers.

Tasks

- Create routes/api.php and move API route definitions there.
- Update RouteServiceProvider to load routes/api.php with api middleware group.
- Add CORS configuration and rate-limiting middleware to the api group.
- Run tests and check endpoints still work.

Acceptance criteria

- API routes served from routes/api.php with proper api middleware.
- CORS headers present on API responses.

Estimated time: 1–2 hours.