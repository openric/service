@php
    $hasChildren = !empty($node['children']);
@endphp
<li role="treeitem">
    @if ($hasChildren)
        <details class="ric-tree-node" data-node-id="{{ $node['id'] }}" x-data="ricTreeNode" open>
            <summary>
                <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $node['id']]) }}">{{ $node['name'] ?? $node['id'] }}</a>
                <code class="ric-id">{{ $node['id'] }}</code>
            </summary>
            <ul>
                @foreach ($node['children'] as $child)
                    @include('ahg-ric-model::partials._hierarchy-node', ['node' => $child, 'version' => $version])
                @endforeach
            </ul>
        </details>
    @else
        <div class="ric-tree-node">
            <span class="ric-tree-leaf">•</span>
            <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $node['id']]) }}">{{ $node['name'] ?? $node['id'] }}</a>
            <code class="ric-id">{{ $node['id'] }}</code>
        </div>
    @endif
</li>
