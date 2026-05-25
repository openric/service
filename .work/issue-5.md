Problem

There are no CI/integration smoke tests to validate key SPARQL queries and endpoints on each push.

Tasks

- Add PHPUnit / Pest tests that exercise core endpoints (GET /api/ric/v1/records, /sparql proxy) and sample SPARQL aggregates.
- Add a GitHub Actions workflow that runs tests and a basic smoke SPARQL query against a mocked Fuseki or a lightweight test container.
- Fail the workflow on parse errors or non-200 endpoints.

Acceptance criteria

- GitHub Actions runs tests on pushes and PRs.
- Smoke tests validate basic SPARQL functionality.

Estimated time: 2–4 hours.