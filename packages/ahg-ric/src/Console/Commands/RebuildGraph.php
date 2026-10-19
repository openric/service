<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Rebuild the public SPARQL graph from the API's own serializer, so the graph
 * says what the API says, under the identifiers the API gives in @id.
 * Design: docs/plans/2026-10-19-sparql-graph-resync-design.md.
 *
 *   php artisan openric:rebuild-graph --dry-run              build and check, change nothing
 *   php artisan openric:rebuild-graph --dataset=openric_staging
 *   php artisan openric:rebuild-graph                        the configured dataset
 *
 * Steps: walk every entity the API's list endpoints return (so the same
 * filters apply), serialise each with the API's show method, and build one
 * JSON-LD document. If its checksum matches the last build, stop: nothing
 * changed. Otherwise load it into a staging named graph, check it, back up
 * the current default graph, and replace the default graph with the staging
 * graph in one SPARQL Update. A failed check leaves the old graph in place
 * and the staging graph for inspection.
 */

namespace AhgRic\Console\Commands;

use AhgRic\Http\Controllers\LinkedDataApiController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RebuildGraph extends Command
{
    protected $signature = 'openric:rebuild-graph
        {--dry-run : Build and check the graph and write it to a file; change nothing in Fuseki}
        {--dataset= : Fuseki dataset to rebuild (default: ahg-ric.fuseki.dataset)}
        {--out= : Also write the JSON-LD document to this file}
        {--force : Replace the graph even if it has not changed since the last build}';

    protected $description = 'Rebuild the public SPARQL graph from the API serializer';

    private const STAGING = 'urn:openric:staging';

    /** API type => [list method, show method, key from the @id's last segment: slug or id]. */
    private const TYPES = [
        'records'        => ['listRecords', 'showRecord', 'slug'],
        'agents'         => ['listAgents', 'showAgent', 'slug'],
        'repositories'   => ['listRepositories', 'showRepository', 'slug'],
        'functions'      => ['listFunctions', 'showFunction', 'id'],
        'places'         => ['listPlaces', 'showPlace', 'id'],
        'rules'          => ['listRules', 'showRule', 'id'],
        'activities'     => ['listActivities', 'showActivity', 'id'],
        'instantiations' => ['listInstantiations', 'showInstantiation', 'id'],
    ];

    private string $fuseki;
    private string $dataset;
    private array $auth;
    private string $stateDir;

    public function handle(): int
    {
        $cfg = config('ahg-ric.fuseki');
        $this->fuseki = rtrim((string) $cfg['url'], '/');
        $this->dataset = (string) ($this->option('dataset') ?: $cfg['dataset']);
        $this->auth = [(string) $cfg['user'], (string) $cfg['pass']];
        $this->stateDir = storage_path('app/openric-graph/' . $this->dataset);
        if (!is_dir($this->stateDir)) {
            mkdir($this->stateDir, 0775, true);
        }
        $started = microtime(true);

        [$context, $docs, $counts, $skipped] = $this->collect();
        $this->line('Entities: ' . json_encode($counts) . ($skipped ? ", skipped (no 200): $skipped" : ''));
        if (!$docs) {
            return $this->failWith('the API returned no entities');
        }

        usort($docs, fn ($a, $b) => strcmp((string) ($a['@id'] ?? ''), (string) ($b['@id'] ?? '')));
        $document = ['@context' => $context, '@graph' => $docs];
        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $checksum = hash('sha256', $json);

        $badIds = $this->foreignIdentifiers($docs);
        if ($badIds) {
            $this->warn(count($badIds) . ' identifiers outside the API scheme, e.g. ' . implode(', ', array_slice($badIds, 0, 3)));
        }

        $last = $this->lastBuild();
        $out = $this->option('out') ?: ($this->option('dry-run') ? $this->stateDir . '/dry-run-' . date('Ymd-His') . '.jsonld' : null);
        if ($out) {
            file_put_contents($out, $json);
            $this->line("JSON-LD written to $out (" . number_format(strlen($json)) . ' bytes)');
        }

        if ($this->option('dry-run')) {
            $this->line("Checksum $checksum" . ($last ? ($last['checksum'] === $checksum ? ' (same as the last build)' : ' (differs from the last build)') : ' (no previous build)'));
            $this->info('Dry run: Fuseki not touched.');
            return $badIds ? self::FAILURE : self::SUCCESS;
        }
        if ($badIds) {
            return $this->failWith('identifiers outside the API scheme');
        }
        if ($last && $last['checksum'] === $checksum && !$this->option('force')) {
            $this->logOutcome('no change', $last['triples'], $checksum, $started);
            return self::SUCCESS;
        }

        // Load into the staging graph and check it there.
        $put = Http::withBasicAuth(...$this->auth)->timeout(300)
            ->withBody($json, 'application/ld+json')
            ->put($this->fuseki . '/' . $this->dataset . '/data?graph=' . urlencode(self::STAGING));
        if (!$put->successful()) {
            return $this->failWith('loading the staging graph failed: HTTP ' . $put->status() . ' ' . substr($put->body(), 0, 300));
        }
        $triples = $this->count('GRAPH <' . self::STAGING . '> { ?s ?p ?o }');
        $problems = $this->check($triples, $last);
        if ($problems) {
            return $this->failWith(implode('; ', $problems) . ' - the old graph is unchanged and the staging graph <' . self::STAGING . '> is kept for inspection');
        }

        // Back up the current default graph, then swap.
        $backup = $this->stateDir . '/backup-' . date('Ymd-His') . '.nt';
        $dump = Http::withBasicAuth(...$this->auth)->timeout(300)->withHeaders(['Accept' => 'application/n-triples'])
            ->get($this->fuseki . '/' . $this->dataset . '/data?default');
        if (!$dump->successful()) {
            return $this->failWith('backing up the default graph failed: HTTP ' . $dump->status());
        }
        file_put_contents($backup, $dump->body());
        $this->pruneBackups(7);

        $swap = $this->update('MOVE <' . self::STAGING . '> TO DEFAULT');
        if (!$swap->successful()) {
            return $this->failWith('the swap failed: HTTP ' . $swap->status() . ' ' . substr($swap->body(), 0, 300) . "; backup at $backup");
        }
        $now = $this->count('?s ?p ?o');
        if ($now !== $triples) {
            return $this->failWith("the default graph holds $now triples after the swap, expected $triples; backup at $backup");
        }

        file_put_contents($this->stateDir . '/last.json', json_encode([
            'checksum' => $checksum, 'triples' => $triples, 'built_at' => date('c'), 'counts' => $counts,
        ], JSON_PRETTY_PRINT));
        $this->logOutcome('replaced', $triples, $checksum, $started, $backup);
        return self::SUCCESS;
    }

    /** Walk every list endpoint and serialise each entity with the API's own show method. */
    private function collect(): array
    {
        $api = app(LinkedDataApiController::class);
        $context = null;
        $docs = [];
        $counts = [];
        $skipped = 0;
        foreach (self::TYPES as $type => [$list, $show, $key]) {
            $counts[$type] = 0;
            for ($page = 1; ; $page++) {
                $request = Request::create('/api/ric/v1/' . $type, 'GET', ['page' => $page, 'limit' => 200]);
                app()->instance('request', $request);
                $body = $api->$list($request)->getData(true);
                $items = $body['openric:items'] ?? [];
                foreach ($items as $item) {
                    $segment = basename(parse_url((string) ($item['@id'] ?? ''), PHP_URL_PATH) ?: '');
                    if ($segment === '') {
                        $skipped++;
                        continue;
                    }
                    $response = $api->$show($key === 'id' ? (int) $segment : $segment);
                    if ($response->getStatusCode() !== 200) {
                        $skipped++;
                        continue;
                    }
                    $doc = $response->getData(true);
                    $context ??= $doc['@context'] ?? null;
                    unset($doc['@context']);
                    $docs[] = $doc;
                    $counts[$type]++;
                }
                if (count($items) < 200) {
                    break;
                }
            }
        }
        return [$context, $docs, $counts, $skipped];
    }

    /** Every IRI-valued @id must use the API's scheme, not the old /entity/ or /default/ ones. */
    private function foreignIdentifiers(array $docs): array
    {
        $bad = [];
        array_walk_recursive($docs, function ($v, $k) use (&$bad) {
            if ($k === '@id' && is_string($v) && preg_match('#^https?://ric\.theahg\.co\.za/(entity|default)/#', $v)) {
                $bad[$v] = true;
            }
        });
        return array_keys($bad);
    }

    private function check(int $triples, ?array $last): array
    {
        $problems = [];
        if ($triples === 0) {
            $problems[] = 'the staging graph is empty';
        }
        if ($last && $last['triples'] > 0 && ($triples < $last['triples'] * 0.5 || $triples > $last['triples'] * 2)) {
            $problems[] = "the staging graph has $triples triples against {$last['triples']} in the last build, outside the expected band";
        }
        $known = $this->ricoTerms();
        if ($known) {
            $used = $this->select('SELECT DISTINCT ?t WHERE { GRAPH <' . self::STAGING . '> { { ?s ?t ?o } UNION { ?s a ?t } } FILTER(STRSTARTS(STR(?t), "https://www.ica.org/standards/RiC/ontology#")) }');
            $unknown = array_values(array_diff(array_map(fn ($t) => substr($t, strlen('https://www.ica.org/standards/RiC/ontology#')), $used), $known));
            if ($unknown) {
                $problems[] = 'terms in the RiC-O namespace that RiC-O 1.1 does not define: rico:' . implode(', rico:', array_slice($unknown, 0, 10));
            }
        }
        return $problems;
    }

    /** Local names of every term RiC-O 1.1 defines, from the copy the service ships. */
    private function ricoTerms(): array
    {
        $file = base_path('vendor/ahg/ric-model/resources/data/ric-o/RiC-O_1-1.rdf');
        if (!is_readable($file)) {
            $this->warn('RiC-O 1.1 file not found; skipping the term check');
            return [];
        }
        preg_match_all('#rdf:about="https://www\.ica\.org/standards/RiC/ontology\#([A-Za-z0-9_]+)"#', file_get_contents($file), $m);
        return array_values(array_unique($m[1]));
    }

    private function count(string $pattern): int
    {
        $rows = $this->select("SELECT (COUNT(*) AS ?n) WHERE { $pattern }");
        return (int) ($rows[0] ?? 0);
    }

    /** Run a SELECT with one variable and return its values. */
    private function select(string $query): array
    {
        $res = Http::withBasicAuth(...$this->auth)->timeout(120)->asForm()
            ->withHeaders(['Accept' => 'application/sparql-results+json'])
            ->post($this->fuseki . '/' . $this->dataset . '/sparql', ['query' => $query]);
        if (!$res->successful()) {
            throw new \RuntimeException('SPARQL query failed: HTTP ' . $res->status());
        }
        $var = $res->json('head.vars.0');
        return array_map(fn ($b) => $b[$var]['value'] ?? null, $res->json('results.bindings') ?? []);
    }

    private function update(string $update)
    {
        return Http::withBasicAuth(...$this->auth)->timeout(300)->asForm()
            ->post($this->fuseki . '/' . $this->dataset . '/update', ['update' => $update]);
    }

    private function lastBuild(): ?array
    {
        $file = $this->stateDir . '/last.json';
        return is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    private function pruneBackups(int $keep): void
    {
        $files = glob($this->stateDir . '/backup-*.nt') ?: [];
        rsort($files);
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }

    private function logOutcome(string $outcome, int $triples, string $checksum, float $started, ?string $backup = null): void
    {
        $line = sprintf('openric:rebuild-graph %s: %s, %d triples, sha256 %s, %.1fs%s',
            $this->dataset, $outcome, $triples, substr($checksum, 0, 12), microtime(true) - $started, $backup ? ", backup $backup" : '');
        Log::info($line);
        $this->info($line);
    }

    private function failWith(string $why): int
    {
        $line = "openric:rebuild-graph {$this->dataset}: FAILED - $why";
        Log::error($line);
        $this->error($line);
        $this->notify($why);
        return self::FAILURE;
    }

    /** Best effort: a bell notification in the Workbench, when its inbox is writable. */
    private function notify(string $why): void
    {
        $inbox = getenv('WORKBENCH_NOTIFICATIONS_INBOX') ?: '/var/spool/workbench/notifications';
        if (is_dir($inbox) && is_writable($inbox)) {
            @file_put_contents($inbox . '/openric-rebuild-graph-' . date('Ymd-His') . '.json', json_encode([
                'username' => 'johan', 'title' => 'OpenRiC graph rebuild failed',
                'message' => "Dataset {$this->dataset}: $why", 'eventType' => 'alert',
            ]));
        }
    }
}
