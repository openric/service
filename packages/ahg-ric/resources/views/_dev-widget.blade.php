{{-- Dev-only standalone render of the _context-sidebar widget. Gated on APP_DEBUG. --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>OpenRiC widget demo — {{ $resourceUri }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <style>
    body { background: #f5f6f8; padding: 2rem 0; }
    .ric-ctx-node { display: block; color: #495057; padding: 0.15rem 0; }
    .ric-ctx-node:hover { color: #0d6efd; text-decoration: underline !important; }
  </style>
</head>
<body>
  <div class="container" style="max-width: 480px;">
    <p class="text-muted small mb-2">
      <strong>Seed:</strong> <code>{{ $resourceUri }}</code>
    </p>
    @include('ahg-ric::_context-sidebar', [
        'resourceUri' => $resourceUri,
        'explorerUrl' => '/admin/ric/explorer',
        'maxNodes'    => 50,
    ])
    <p class="small text-muted mt-3">
      Try other seeds:
      <a href="?uri={{ urlencode('https://ric.theahg.co.za/entity/record/553') }}">record/553</a> ·
      <a href="?uri={{ urlencode('https://ric.theahg.co.za/entity/record/901990') }}">record/901990</a> ·
      <a href="?uri={{ urlencode('https://ric.theahg.co.za/entity/recordset/983') }}">recordset/983</a>
    </p>
  </div>
</body>
</html>
