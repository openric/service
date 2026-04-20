<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RiC-CM Reference — unavailable</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<main class="container py-5">
    <div class="col-lg-8 mx-auto">
        <h1 class="h3 mb-3">RiC-CM reference is temporarily unavailable</h1>
        <p class="text-muted">The ontology service (Apache Jena Fuseki) could not be reached or returned an unexpected response. This is a configuration or infrastructure issue, not missing data.</p>

        <div class="alert alert-warning small mt-4">
            <strong>Upstream error:</strong><br>
            <code>{{ $reason ?? 'Unknown error' }}</code>
        </div>

        <h2 class="h6 text-uppercase text-muted mt-4">Configuration</h2>
        <dl class="row small">
            <dt class="col-sm-3">Endpoint</dt>
            <dd class="col-sm-9"><code>{{ $fuseki['url'] ?? '—' }}</code></dd>

            <dt class="col-sm-3">Dataset</dt>
            <dd class="col-sm-9"><code>{{ $fuseki['dataset'] ?? '—' }}</code></dd>

            <dt class="col-sm-3">Auth</dt>
            <dd class="col-sm-9">{{ !empty($fuseki['user']) ? 'configured' : 'anonymous' }}</dd>
        </dl>

        <h2 class="h6 text-uppercase text-muted mt-4">What to check</h2>
        <ol class="small">
            <li>Fuseki process is running: <code>curl {{ $fuseki['url'] ?? '...' }}/$/ping</code></li>
            <li>Dataset <code>{{ $fuseki['dataset'] ?? '—' }}</code> exists and is loaded with RiC-O OWL. See <code>packages/ahg-ric-model/resources/data/ric-o/loaded-versions.md</code> for the reload command.</li>
            <li><code>FUSEKI_URL</code>, <code>FUSEKI_DATASET_MODEL</code>, <code>FUSEKI_USER</code>, <code>FUSEKI_PASSWORD</code> in <code>.env</code> match the running instance.</li>
            <li>Run <code>php artisan ric-model:rebuild-cache</code> after fixing, to clear any stale cached errors.</li>
        </ol>
    </div>
</main>
</body>
</html>
