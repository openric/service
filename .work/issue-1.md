Problem

SPARQL aggregate/alias syntax used in the codebase is incompatible with Fuseki 4.x. Queries using aliased aggregates (e.g. `SELECT (COUNT(*) AS cnt)`) fail or return wrong results.

Tasks

- Search and fix SPARQL alias usages to prefix variable names with `?` (e.g. `(COUNT(*) AS ?cnt)`).
- Add unit/integration tests for aggregate queries against a test Fuseki dataset or mocked SPARQL endpoint.
- Run full test suite and fix any remaining parse errors.
- Document Fuseki 4.x compatibility in docs/deployment.md.

Acceptance criteria

- No SPARQL parse errors in tests.
- Aggregate queries return expected counts.

Estimated time: 2–4 hours.