{{--
  RiC Context Sidebar — relationships for an entity, grouped by type.

  Usage:
    @include('ahg-ric::_context-sidebar', [
        'resourceUri' => 'https://ric.theahg.co.za/entity/record/553',
        'explorerUrl' => '/explorer',          // optional CTA target
        'maxNodes'    => 50,                    // optional, 1..500
        'title'       => 'Relationships',       // optional heading
    ])

  Host app is responsible for ACL — only pass $resourceUri for entities
  the current user is allowed to see. The widget does not filter further.
--}}
@php
  $resourceUri = $resourceUri ?? null;
  $explorerUrl = $explorerUrl ?? null;
  $maxNodes    = (int) ($maxNodes ?? 50);
  $title       = $title ?? 'Relationships';
  $widgetId    = 'ric-ctx-' . substr(md5($resourceUri ?? ''), 0, 8);
@endphp

@if($resourceUri)
<section id="{{ $widgetId }}" class="card mb-3 ric-context-widget" data-resource-uri="{{ $resourceUri }}" data-max-nodes="{{ $maxNodes }}">
  <div class="card-header bg-success text-white py-2">
    <h6 class="mb-0"><i class="fas fa-sitemap me-2"></i>{{ $title }}</h6>
  </div>
  <div class="card-body p-0 ric-context-body">
    <div class="text-center py-3">
      <div class="spinner-border spinner-border-sm text-success" role="status">
        <span class="visually-hidden">Loading…</span>
      </div>
      <p class="small text-muted mt-1 mb-0">Loading context…</p>
    </div>
  </div>
  @if($explorerUrl)
  <div class="card-footer py-2">
    <a href="{{ $explorerUrl }}?seed={{ urlencode($resourceUri) }}" class="btn btn-sm btn-outline-success w-100">
      <i class="fas fa-project-diagram me-1"></i>Open in Graph Explorer
    </a>
  </div>
  @endif
</section>

<script>
(function(){
  var root = document.getElementById(@json($widgetId));
  if (!root) return;
  var body = root.querySelector('.ric-context-body');
  var uri  = root.dataset.resourceUri;
  var max  = parseInt(root.dataset.maxNodes, 10) || 50;

  var typeIcons = {
    'RecordSet':'fas fa-folder text-info',
    'Record':'fas fa-file-alt text-info',
    'RecordPart':'fas fa-file text-info',
    'Person':'fas fa-user text-danger',
    'CorporateBody':'fas fa-building text-warning',
    'Family':'fas fa-users text-danger',
    'Activity':'fas fa-bolt text-purple',
    'Function':'fas fa-cogs text-purple',
    'Place':'fas fa-map-marker-alt text-orange',
    'Concept':'fas fa-tag text-success',
    'AgentName':'fas fa-signature text-muted',
    'PlaceName':'fas fa-map text-muted',
    'DateRange':'fas fa-calendar text-muted'
  };
  var typeOrder = ['Person','CorporateBody','Family','RecordSet','Record','RecordPart','Place','Activity','Function','Concept'];

  function esc(s) { return String(s).replace(/[&<>"']/g, function(c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function slug(type) { return type.toLowerCase(); }

  var endpoint = '/ric-api/graph-summary-by-uri?uri=' + encodeURIComponent(uri) + '&max_nodes=' + max;

  fetch(endpoint, { headers: { 'Accept': 'application/json' } })
    .then(function(r){ return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
    .then(function(data){
      var graph = (data && data.graph) || null;
      if (!data.success || !graph || !Array.isArray(graph.nodes) || graph.nodes.length <= 1) {
        body.innerHTML = '<div class="p-3 text-muted small">No related entities in the store.</div>';
        return;
      }

      var centerId = null;
      var groups = {};
      graph.nodes.forEach(function(n){
        if (n.type === 'center') { centerId = n.id; return; }
        var t = n.type || 'Other';
        (groups[t] = groups[t] || []).push(n);
      });

      var types = Object.keys(groups).sort(function(a, b){
        var ai = typeOrder.indexOf(a), bi = typeOrder.indexOf(b);
        return (ai === -1 ? 999 : ai) - (bi === -1 ? 999 : bi);
      });

      var html = '<div class="list-group list-group-flush">';
      types.forEach(function(t){
        var items = groups[t];
        var icon = typeIcons[t] || 'fas fa-circle text-secondary';
        html += '<div class="list-group-item px-3 py-2">';
        html +=   '<div class="d-flex justify-content-between align-items-center mb-1">';
        html +=     '<strong class="small"><i class="' + icon + ' me-1"></i>' + esc(t) + '</strong>';
        html +=     '<span class="badge bg-secondary">' + items.length + '</span>';
        html +=   '</div>';
        items.slice(0, 5).forEach(function(n){
          var label = n.label || '(unnamed)';
          if (label.length > 60) label = label.substring(0, 60) + '…';
          html += '<a class="d-block small text-decoration-none ps-3 ric-ctx-node" href="' + esc(n.id) + '" title="' + esc(n.id) + '">' + esc(label) + '</a>';
        });
        if (items.length > 5) {
          html += '<div class="small text-muted ps-3 fst-italic">+ ' + (items.length - 5) + ' more</div>';
        }
        html += '</div>';
      });
      html += '</div>';

      var total = graph.total_nodes - 1;
      var rels  = graph.total_edges;
      html += '<div class="px-3 py-2 border-top small text-muted">';
      html +=   '<i class="fas fa-info-circle me-1"></i>' + total + ' related, ' + rels + ' relationships';
      if (graph.truncated) {
        html += ' <span class="badge bg-warning text-dark ms-1">capped at ' + graph.max_nodes + '</span>';
      }
      html += '</div>';

      body.innerHTML = html;
    })
    .catch(function(err){
      body.innerHTML = '<div class="p-3 text-danger small">Could not load relationships: ' + esc(err.message) + '</div>';
    });
})();
</script>
@endif
