// OpenRiC Graph Explorer — 2D/3D force-directed visualisation over /openric.
// Consumed by packages/ahg-ric/resources/views/explorer.blade.php.

import ForceGraph from 'force-graph';
import ForceGraph3D from '3d-force-graph';

const API = {
  search: '/ric-api/search',
  summary: '/ric-api/graph-summary-by-uri',
  expand: '/ric-api/expand',
};

const TYPE_COLOR = {
  center:        '#ffd166',
  Record:        '#45b7d1',
  RecordSet:     '#4ecdc4',
  RecordPart:    '#89c2d9',
  Person:        '#dc3545',
  CorporateBody: '#ffc107',
  Family:        '#e83e8c',
  Activity:      '#6f42c1',
  Event:         '#6f42c1',
  Place:         '#fd7e14',
  PlaceName:     '#ffab73',
  AgentName:     '#adb5bd',
  Concept:       '#20c997',
  DateRange:     '#6c757d',
};

function colorFor(type) { return TYPE_COLOR[type] || '#9ca3af'; }

function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function el(id) { return document.getElementById(id); }

function escapeHtml(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : String(s);
  return div.innerHTML;
}

// State machine across 2D/3D/fullscreen modes.
class Explorer {
  constructor(root) {
    this.root = root;
    this.state = {
      nodes: new Map(),  // id → node object
      edges: [],         // {source, target, label} — source/target are node objects after init
      seedUri: null,
      view: '2d',
      instance: null,
      fsInstance: null,
      truncated: false,
    };

    this.container2d  = el('ric-explorer-graph-2d');
    this.container3d  = el('ric-explorer-graph-3d');
    this.containerFs  = el('ric-fullscreen-graph');
    this.placeholder  = el('ric-explorer-placeholder');
    this.loading      = el('ric-explorer-loading');
    this.nodeCount    = el('ric-node-count');
    this.legend       = el('ric-explorer-legend');
    this.searchInput  = el('ric-autocomplete-input');
    this.dropdown     = el('ric-autocomplete-dropdown');

    this.bindEvents();
  }

  bindEvents() {
    this.searchInput.addEventListener('input', debounce(e => this.onSearchInput(e), 250));
    document.addEventListener('click', e => {
      if (!this.dropdown.contains(e.target) && e.target !== this.searchInput) {
        this.dropdown.style.display = 'none';
      }
    });
    document.querySelectorAll('.ric-view-btn').forEach(btn => {
      btn.addEventListener('click', () => this.switchView(btn.dataset.view));
    });
    el('ric-fullscreen-btn').addEventListener('click', () => this.openFullscreen());
    el('ric-close-fullscreen').addEventListener('click', () => this.closeFullscreen());
    document.querySelectorAll('.ric-fs-view-btn').forEach(btn => {
      btn.addEventListener('click', () => this.switchFullscreenView(btn.dataset.view));
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') this.closeFullscreen(); });
  }

  async onSearchInput(e) {
    const q = (e.target.value || '').trim();
    if (q.length < 2) { this.dropdown.style.display = 'none'; return; }
    try {
      const r = await fetch(`${API.search}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const data = await r.json();
      this.renderSearchResults(data.results || []);
    } catch (_) {
      this.dropdown.style.display = 'none';
    }
  }

  renderSearchResults(results) {
    if (!results.length) { this.dropdown.style.display = 'none'; return; }
    this.dropdown.innerHTML = results.map(r => {
      const t = escapeHtml(r.type || '');
      const l = escapeHtml(r.label || '(unnamed)');
      return `<a href="#" class="list-group-item list-group-item-action ric-ac-item" data-uri="${escapeHtml(r.uri)}">
        <div class="d-flex justify-content-between"><span>${l}</span><span class="badge bg-info">${t}</span></div>
      </a>`;
    }).join('');
    this.dropdown.style.display = 'block';
    this.dropdown.querySelectorAll('.ric-ac-item').forEach(a => {
      a.addEventListener('click', e => {
        e.preventDefault();
        this.dropdown.style.display = 'none';
        this.searchInput.value = a.querySelector('span').textContent;
        this.seed(a.dataset.uri);
      });
    });
  }

  async seed(uri, maxNodes = 50) {
    this.placeholder.style.display = 'none';
    this.loading.style.display = 'flex';
    this.destroyInstance();
    this.state.nodes = new Map();
    this.state.edges = [];
    this.state.seedUri = uri;

    try {
      const r = await fetch(`${API.summary}?uri=${encodeURIComponent(uri)}&max_nodes=${maxNodes}`, { headers: { Accept: 'application/json' } });
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const data = await r.json();
      if (!data.success || !data.graph || !data.graph.nodes.length) {
        this.showPlaceholder('No relationships found for this entity.');
        return;
      }
      this.ingest(data.graph);
      this.render();
    } catch (err) {
      this.showPlaceholder(`Error loading graph: ${escapeHtml(err.message)}`, true);
    }
  }

  async expand(uri, maxNodes = 25) {
    try {
      const r = await fetch(`${API.expand}?node=${encodeURIComponent(uri)}&max_nodes=${maxNodes}`, { headers: { Accept: 'application/json' } });
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const data = await r.json();
      if (!data.success || !data.graph) return;
      this.ingest(data.graph, uri);
      if (this.state.instance) {
        const d = this.graphData();
        this.state.instance.graphData(d);
      } else {
        this.render();
      }
    } catch (_) { /* silent — explorer keeps its current view */ }
  }

  ingest(graph, originUri = null) {
    (graph.nodes || []).forEach(n => {
      const id = n.id;
      if (!this.state.nodes.has(id)) {
        this.state.nodes.set(id, {
          id,
          label: n.label || id,
          type:  n.type  || 'Unknown',
          color: colorFor(n.type || 'Unknown'),
          val:   n.type === 'center' || id === this.state.seedUri ? 8 : 4,
        });
      }
    });
    (graph.edges || []).forEach(e => {
      // Deduplicate
      const dup = this.state.edges.find(x =>
        (x.sourceId === e.source && x.targetId === e.target && x.label === e.label)
     || (x.sourceId === e.target && x.targetId === e.source && x.label === e.label));
      if (!dup) this.state.edges.push({ sourceId: e.source, targetId: e.target, label: e.label });
    });
    this.state.truncated = !!graph.truncated;
  }

  graphData() {
    const nodesArr = Array.from(this.state.nodes.values());
    const byId = new Map(nodesArr.map(n => [n.id, n]));
    const linksArr = this.state.edges
      .filter(e => byId.has(e.sourceId) && byId.has(e.targetId))
      .map(e => ({ source: e.sourceId, target: e.targetId, label: e.label }));
    return { nodes: nodesArr, links: linksArr };
  }

  updateStatus() {
    const n = this.state.nodes.size;
    const chip = this.state.truncated ? ' (capped)' : '';
    this.nodeCount.textContent = `${n} nodes${chip}`;
    this.legend.style.display = n > 0 ? 'block' : 'none';
  }

  destroyInstance() {
    if (this.state.instance) {
      try { this.state.instance._destructor && this.state.instance._destructor(); } catch (_) {}
      this.state.instance = null;
    }
    this.container2d.innerHTML = '';
    this.container3d.innerHTML = '';
    this.container2d.style.display = 'none';
    this.container3d.style.display = 'none';
  }

  render() {
    this.loading.style.display = 'none';
    this.destroyInstance();
    const data = this.graphData();
    if (this.state.view === '3d') {
      this.container3d.style.display = 'block';
      this.state.instance = ForceGraph3D()(this.container3d)
        .backgroundColor('#1a1a2e')
        .nodeLabel(n => `${n.type}: ${n.label}`)
        .nodeColor(n => n.color)
        .nodeVal(n => n.val)
        .linkLabel(l => l.label)
        .linkDirectionalArrowLength(3)
        .linkDirectionalArrowRelPos(1)
        .onNodeClick(n => this.expand(n.id))
        .graphData(data);
    } else {
      this.container2d.style.display = 'block';
      this.state.instance = ForceGraph()(this.container2d)
        .backgroundColor('#1a1a2e')
        .nodeLabel(n => `${n.type}: ${n.label}`)
        .nodeColor(n => n.color)
        .nodeVal(n => n.val)
        .linkColor(() => '#6b7280')
        .linkDirectionalArrowLength(4)
        .linkDirectionalArrowRelPos(1)
        .onNodeClick(n => this.expand(n.id))
        .nodeCanvasObject((n, ctx, scale) => {
          const r = Math.max(3, n.val);
          ctx.beginPath();
          ctx.arc(n.x, n.y, r, 0, 2 * Math.PI);
          ctx.fillStyle = n.color;
          ctx.fill();
          const label = (n.label || '').slice(0, 40);
          if (scale > 1.2 && label) {
            ctx.font = `${Math.max(8, 10 / scale)}px sans-serif`;
            ctx.fillStyle = '#e5e7eb';
            ctx.textAlign = 'center';
            ctx.fillText(label, n.x, n.y + r + 6);
          }
        })
        .graphData(data);
    }
    this.updateStatus();
  }

  switchView(view) {
    if (view !== '2d' && view !== '3d') return;
    this.state.view = view;
    document.querySelectorAll('.ric-view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === view));
    if (this.state.nodes.size) this.render();
  }

  openFullscreen() {
    if (!this.state.nodes.size) return;
    const modal = el('ric-fullscreen-modal');
    modal.style.display = 'block';
    this.containerFs.innerHTML = '';

    const data = this.graphData();
    if (this.state.view === '3d') {
      this.state.fsInstance = ForceGraph3D()(this.containerFs)
        .backgroundColor('#1a1a2e')
        .nodeColor(n => n.color)
        .nodeLabel(n => `${n.type}: ${n.label}`)
        .linkDirectionalArrowLength(3)
        .linkDirectionalArrowRelPos(1)
        .graphData(data);
    } else {
      this.state.fsInstance = ForceGraph()(this.containerFs)
        .backgroundColor('#1a1a2e')
        .nodeColor(n => n.color)
        .nodeLabel(n => `${n.type}: ${n.label}`)
        .linkColor(() => '#6b7280')
        .linkDirectionalArrowLength(4)
        .linkDirectionalArrowRelPos(1)
        .graphData(data);
    }
    document.querySelectorAll('.ric-fs-view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === this.state.view));
  }

  closeFullscreen() {
    const modal = el('ric-fullscreen-modal');
    if (!modal) return;
    modal.style.display = 'none';
    if (this.state.fsInstance) {
      try { this.state.fsInstance._destructor && this.state.fsInstance._destructor(); } catch (_) {}
      this.state.fsInstance = null;
    }
    this.containerFs.innerHTML = '';
  }

  switchFullscreenView(view) {
    this.state.view = view;
    this.closeFullscreen();
    // Re-open at new view
    el('ric-fullscreen-modal').style.display = 'block';
    this.containerFs.innerHTML = '';
    const data = this.graphData();
    if (view === '3d') {
      this.state.fsInstance = ForceGraph3D()(this.containerFs).backgroundColor('#1a1a2e').nodeColor(n => n.color).nodeLabel(n => `${n.type}: ${n.label}`).graphData(data);
    } else {
      this.state.fsInstance = ForceGraph()(this.containerFs).backgroundColor('#1a1a2e').nodeColor(n => n.color).nodeLabel(n => `${n.type}: ${n.label}`).linkColor(() => '#6b7280').graphData(data);
    }
    document.querySelectorAll('.ric-fs-view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === view));
  }

  showPlaceholder(msg, isError = false) {
    this.loading.style.display = 'none';
    this.placeholder.style.display = 'flex';
    this.placeholder.innerHTML = `<div class="text-center ${isError ? 'text-danger' : 'text-warning'}"><i class="fas fa-info-circle fa-2x mb-2"></i><p>${msg}</p></div>`;
  }
}

window.RicExplorer = {
  boot(rootEl) {
    const explorer = new Explorer(rootEl);
    // ?uri=<seed> boots the explorer directly
    const params = new URLSearchParams(window.location.search);
    const seed = params.get('uri') || params.get('seed');
    if (seed) explorer.seed(seed);
    return explorer;
  },
};
