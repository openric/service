<?php

declare(strict_types=1);

namespace AhgRicModel\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class LoadOntologyCommand extends Command
{
    protected $signature = 'ric-model:load-ontology
                            {file                  : Path to the RDF/XML or Turtle file to load}
                            {--dataset=            : Fuseki dataset name (defaults to FUSEKI_DATASET_MODEL)}
                            {--replace             : Use PUT (replace default graph) instead of POST (append)}
                            {--format=rdf+xml      : Content-Type: application/<format>  (rdf+xml | turtle | n-triples)}';

    protected $description = 'Load an ontology file into Fuseki via the Graph Store Protocol.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $fuseki   = (array) config('ahg-ric-model.fuseki');
        $url      = (string) ($fuseki['url'] ?? '');
        $dataset  = (string) ($this->option('dataset') ?: ($fuseki['dataset'] ?? ''));
        $user     = $fuseki['user']     ?? null;
        $password = $fuseki['password'] ?? null;

        if ($url === '' || $dataset === '') {
            $this->error('Fuseki URL and dataset must be configured (see config/ahg-ric-model.php and .env).');
            return self::FAILURE;
        }

        $format  = (string) $this->option('format');
        $contentType = 'application/' . $format;

        $this->info("Loading {$file}");
        $this->line("  → {$url}/{$dataset}/data?default  ({$contentType})");

        $client = new Client([
            'base_uri' => rtrim($url, '/') . '/',
            'timeout'  => 120,
            'auth'     => ($user !== null && $user !== '') ? [$user, $password ?? ''] : null,
        ]);

        $method = $this->option('replace') ? 'PUT' : 'POST';

        try {
            $response = $client->request($method, $dataset . '/data?default', [
                'headers' => ['Content-Type' => $contentType],
                'body'    => fopen($file, 'rb'),
            ]);
        } catch (GuzzleException $e) {
            $this->error('Load failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $status = $response->getStatusCode();
        $body   = (string) $response->getBody();

        $this->line("  HTTP {$status}");
        if ($body !== '') {
            $this->line('  ' . trim($body));
        }

        if ($status < 200 || $status >= 300) {
            return self::FAILURE;
        }

        $this->info('');
        $this->info('Loaded. Run `php artisan ric-model:rebuild-cache` to refresh cached views.');
        return self::SUCCESS;
    }
}
