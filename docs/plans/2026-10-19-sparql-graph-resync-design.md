# Public SPARQL graph: resync design

Status: proposed, for Dr Johan Pieterse. 19 October 2026. Nothing here is implemented.

## The problem

The reference service's public SPARQL endpoint (`/api/ric/v1/sparql`) answers from its own Fuseki dataset, `openric`, which nothing keeps up to date. It was loaded once; the old sync cron (`/usr/share/nginx/archive/ric_sync_v2.sh`, in `/etc/cron.d/ric_cron.disabled`) is switched off, and the nightly demonstration reset restores MySQL only. As a result:

- **It drifts from the data.** Records deleted or renamed in the database stay in the graph; on 16 October four image records that no longer exist were still there, and records added since the load are missing. Rights removals have had to be done by hand (43 triples on 16 October).
- **It names things differently from the API.** One record is published under three identifiers, and only one of them works:

| Where | Identifier for the Egyptian Boat | Dereferences |
|---|---|---|
| API (`/records/egyptian-boat`, `@id`) | `https://ric.theahg.co.za/informationobject/egyptian-boat` | yes: 302 to `/api/ric/v1/records/egyptian-boat` |
| `/graph` node ids | `https://ric.theahg.co.za/default/recordset/905228` | no: 404 |
| Public SPARQL graph | `https://ric.theahg.co.za/entity/record/905228` | no: 404 |

  A client that follows a node from `/graph` or a SPARQL result cannot fetch it. Graph Primitives and Core Discovery both expect an entity's identifier to be the one a client can dereference.

## Constraints

- **Heratio writes to the same database in-process**, bypassing the service. A sync driven by the service's own write path would miss Heratio's changes, so the projection has to be built from the database, not from the service's requests.
- **The database is reset every night at 02:20** from the demonstration snapshot.
- **The graph must say what the API says.** The API's output already follows the specification (RiC-O 1.1 names, relation predicates mapped by `RicoTerms`, scope and content as `rico:scopeAndContent`). A second producer (the Python extractor, `ric_extractor_v5.py`) has its own mapping and its own identifier scheme, which is how the two came to differ.
- **Fixtures.** No published fixture pins the SPARQL graph's identifiers: `sparql-construct` is an authored example on `archives.example.org`, and `sparql-info` describes the dataset only. `subgraph-depth-1` and `-2` pin `/graph`'s `default/...` identifiers, so changing those is a fixture change.
- **Load.** The demonstration dataset is small (about 7,000 triples); a full rebuild takes seconds. No GPU or AI work is involved.

## Options

1. **Scheduled full rebuild from the service's serializer.** A console command walks every published entity, serialises it with the same code the API uses (`RicSerializationService`), converts the JSON-LD to N-Quads, loads it into a fresh named graph, checks it, and only then swaps it in as the dataset's default graph. Run nightly after the 02:20 reset, and on demand after a rights removal.
2. **Rebuild with the Python extractor.** Re-enable `ric_sync.sh`. Rejected: the extractor's mapping and identifiers differ from the API's, which is the problem being solved.
3. **Incremental sync from the service's write path** (an outbox). Rejected for now: it misses Heratio's in-process writes and the nightly reset. It becomes right when the reference service has a database of its own (known issue K-9) and is the only writer.
4. **No triplestore: answer SPARQL from the API's data on the fly.** Rejected: SPARQL Access is about a real SPARQL 1.1 endpoint.

## Proposal

Option 1, with one identifier scheme.

- **Identifiers.** Every entity in the graph uses the identifier the API gives it in `@id`, the only one that dereferences. The `entity/...` scheme disappears from the graph. Separately, a service change makes `/graph` node ids the same `@id` values, and the two subgraph fixtures are re-captured in that release.
- **Command.** `php artisan openric:rebuild-graph [--dry-run]`:
  1. list published records, agents, repositories, functions, places, rules, activities and instantiations, through the same filters the API's list endpoints apply (at least `OpenWriteFilter`; which publication and embargo rules apply is checked when the command is built), so nothing the API would not show enters the graph;
  2. serialise each with `RicSerializationService`, collect the JSON-LD and parse it to N-Quads;
  3. load it into a staging named graph in the `openric` dataset;
  4. check: triple count within an expected band of the last build, every published entity present, no `rico:` term outside RiC-O 1.1, no identifier outside the API's scheme;
  5. only if the checks pass, replace the default graph with the staging graph in one SPARQL Update (`DROP` then `ADD`, or `MOVE`), keep the previous build as a backup file, and write a one-line report (counts, duration, checksum) to the log;
  6. if a check fails, keep the old graph, keep the staging graph for inspection, and raise a bell notification.
- **Schedule.** Nightly at 03:00, after the 02:20 reset, through the service's scheduler (`routes/console.php`), so it runs as www-data like the daily digest. On demand with the same command after a rights or privacy removal.
- **Credentials.** The Fuseki user and password the service already holds in `.env`.

## Rollout

1. Build the command and its checks; tests on the staging copy, against a disposable Fuseki dataset (for example `openric_staging`), never `openric`.
2. Dry run against production, read-only: report what would be loaded and the differences from today's graph, for review.
3. First real rebuild by hand, with a fresh full backup taken just before it, and the 16 October backup (`fuseki-openric-backup-20261016-111349.nq`, taken before the rights removal) kept as well.
4. Then schedule it.
5. In the same service release, align `/graph` node ids with `@id`, and re-capture `subgraph-depth-1` and `-2`.

## What it closes

- The drift-log rows "The public SPARQL graph is a static load" and the stale entities it causes.
- The identifier mismatch above, which is not yet in the drift log and should be added.
- Hand rights removals: a removal in the database reaches the graph by the next morning, or at once on demand.

## What it does not close

- Heratio's own triplestore and its extractor, which are Heratio's.
- The reference service's dependency on a database shared with Heratio (K-9); the outbox design (option 3) belongs to the day the service has its own database.

## Decisions needed

1. Option 1, and one identifier scheme taken from the API's `@id`.
2. Whether `/graph` node ids change in the same release (a fixture change and a visible change for clients that stored those ids).
3. Nightly at 03:00, after the reset.
