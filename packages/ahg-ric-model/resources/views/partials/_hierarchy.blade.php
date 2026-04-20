{{--
  Expandable RiC-CM class hierarchy.

  Progressive enhancement: built on native <details>/<summary>, so keyboard
  navigation, expand/collapse, and screen-reader semantics work with zero
  JS. Alpine layers on top to persist open/closed state per node to
  sessionStorage, so a user's expansions survive page navigation.

  Call sites pass `$nodes` (the tree shape returned by
  OntologyService::getHierarchy()) and `$version`.
--}}
@php
    $nodes   = $nodes   ?? [];
    $version = $version ?? config('ahg-ric-model.versions.latest');
@endphp

<script>
    /*
     * One-time registration — script runs only once regardless of how many
     * times this partial is included on a page.
     */
    if (!window.ricHierarchyInit) {
        window.ricHierarchyInit = true;
        document.addEventListener('alpine:init', () => {
            Alpine.data('ricTreeNode', () => ({
                init() {
                    const id = this.$el.dataset.nodeId;
                    if (!id) return;
                    try {
                        const stored = sessionStorage.getItem('ric-tree:' + id);
                        if (stored === 'open')  this.$el.open = true;
                        if (stored === 'closed') this.$el.open = false;
                    } catch (e) { /* storage disabled — harmless */ }
                    this.$el.addEventListener('toggle', () => {
                        try {
                            sessionStorage.setItem('ric-tree:' + id, this.$el.open ? 'open' : 'closed');
                        } catch (e) { /* storage disabled */ }
                    });
                },
            }));
        });
    }
</script>

<style>
    .ric-tree { list-style: none; padding-left: 0; margin: 0; }
    .ric-tree ul { list-style: none; padding-left: 1.1rem; margin: 0; border-left: 1px dashed #dee2e6; }
    .ric-tree-node { margin: 0.1rem 0; }
    .ric-tree-node > summary { cursor: pointer; padding: 0.15rem 0.25rem; border-radius: 0.2rem; list-style: none; }
    .ric-tree-node > summary::-webkit-details-marker { display: none; }
    .ric-tree-node > summary::before {
        content: "▶"; display: inline-block; width: 1em; color: #adb5bd;
        transition: transform 0.12s ease-in-out;
    }
    .ric-tree-node[open] > summary::before { transform: rotate(90deg); }
    .ric-tree-leaf { display: inline-block; width: 1em; color: transparent; }
    .ric-tree-node > summary:hover { background: #f8f9fa; }
    .ric-tree-node > summary:focus-visible { outline: 2px solid #1c7ed6; outline-offset: 1px; }
</style>

<ul class="ric-tree" role="tree">
    @foreach ($nodes as $node)
        @include('ahg-ric-model::partials._hierarchy-node', ['node' => $node, 'version' => $version])
    @endforeach
</ul>
