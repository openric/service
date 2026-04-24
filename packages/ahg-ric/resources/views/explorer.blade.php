{{-- OpenRiC Graph Explorer — standalone page. Self-contained (no theme layout
     dependency), bundles force-graph/3d-force-graph via Vite. --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>OpenRiC Graph Explorer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  @vite(['resources/js/explorer.js'])
  <style>
    body { background: #f5f6f8; }
    .ric-legend { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,.6); color: #fff; padding: .5rem .75rem; border-radius: .25rem; font-size: .75rem; }
    .ric-legend-item { display: flex; align-items: center; gap: .4rem; margin-bottom: .15rem; }
    .ric-legend-color { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
    #ric-autocomplete-dropdown { max-height: 320px; overflow-y: auto; }
    .ric-view-btn.active { background: #0d6efd; color: #fff; }
    .ric-fs-view-btn.active { background: #0d6efd; color: #fff; }
  </style>
</head>
<body>
<div class="container-fluid py-3">

  <div class="d-flex align-items-center mb-3">
    <i class="fas fa-2x fa-project-diagram text-success me-3"></i>
    <div>
      <h2 class="mb-0">OpenRiC Graph Explorer</h2>
      <span class="small text-muted">Records in Contexts — {{ config('app.name', 'OpenRiC') }}</span>
    </div>
  </div>

  {{-- Search / Autocomplete Bar --}}
  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="row align-items-center">
        <div class="col-md-8 position-relative">
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="ric-autocomplete-input" class="form-control"
                   placeholder="Search entities by title, label, or name (min 2 chars)…"
                   autocomplete="off" />
          </div>
          <div id="ric-autocomplete-dropdown" class="list-group position-absolute shadow-sm" style="display:none; z-index:1050; width: calc(100% - 24px);"></div>
        </div>
        <div class="col-md-4 text-end mt-2 mt-md-0">
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary ric-view-btn active" data-view="2d">2D</button>
            <button type="button" class="btn btn-outline-secondary ric-view-btn" data-view="3d">3D</button>
            <button type="button" class="btn btn-outline-secondary" id="ric-fullscreen-btn" title="Fullscreen">
              <i class="fas fa-expand"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Graph Container --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center bg-success text-white">
      <span><i class="fas fa-project-diagram me-1"></i>Graph view</span>
      <span id="ric-node-count" class="badge bg-light text-dark">0 nodes</span>
    </div>
    <div class="card-body p-0">
      <div id="ric-explorer-root" class="position-relative" style="height: 600px; background:#1a1a2e;">
        <div id="ric-explorer-placeholder" class="d-flex align-items-center justify-content-center h-100 text-white">
          <div class="text-center">
            <i class="fas fa-project-diagram fa-3x mb-2 opacity-50"></i>
            <p class="mb-0">Search for an entity to seed the graph, or open a direct link with <code>?uri=…</code>.</p>
          </div>
        </div>
        <div id="ric-explorer-loading" class="align-items-center justify-content-center h-100" style="display: none;">
          <div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
        <div id="ric-explorer-graph-2d" class="position-absolute top-0 start-0 w-100 h-100" style="display:none;"></div>
        <div id="ric-explorer-graph-3d" class="position-absolute top-0 start-0 w-100 h-100" style="display:none;"></div>

        <div id="ric-explorer-legend" class="ric-legend" style="display:none;">
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#ffd166"></span>Seed</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#45b7d1"></span>Record</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#4ecdc4"></span>RecordSet</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#dc3545"></span>Person</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#ffc107"></span>CorporateBody</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#fd7e14"></span>Place</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#6f42c1"></span>Activity</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#20c997"></span>Concept</div>
        </div>
      </div>
    </div>
    <div class="card-footer small text-muted">
      Click a node to expand its neighbourhood. Max 50 nodes on seed, +25 per expansion.
    </div>
  </div>

  {{-- Fullscreen Modal --}}
  <div id="ric-fullscreen-modal" style="display:none; position:fixed; inset:0; background:#1a1a2e; z-index:9999;">
    <div style="position:absolute; top:15px; right:15px; z-index:10001;">
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-light ric-fs-view-btn active" data-view="2d">2D</button>
        <button type="button" class="btn btn-light ric-fs-view-btn" data-view="3d">3D</button>
      </div>
      <button type="button" class="btn btn-danger btn-sm ms-2" id="ric-close-fullscreen">
        <i class="fas fa-times"></i> Close
      </button>
    </div>
    <div id="ric-fullscreen-graph" style="width:100%; height:100%;"></div>
  </div>

</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.RicExplorer && typeof window.RicExplorer.boot === 'function') {
      window.RicExplorer.boot(document.getElementById('ric-explorer-root'));
    }
  });
</script>
</body>
</html>
