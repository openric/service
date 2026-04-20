@props(['nodes', 'version'])
<ul class="mb-0" style="padding-left: 1.25rem;">
    @foreach ($nodes as $node)
        <li>
            <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $node['id']]) }}">{{ $node['name'] ?? $node['id'] }}</a>
            <code class="ric-id">{{ $node['id'] }}</code>
            @if (!empty($node['children']))
                @include('ahg-ric-model::partials._tree-node', ['nodes' => $node['children'], 'version' => $version])
            @endif
        </li>
    @endforeach
</ul>
