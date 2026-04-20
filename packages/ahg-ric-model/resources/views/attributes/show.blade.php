@extends('ahg-ric-model::partials._layout', ['pageTitle' => ($attribute['name'] ?? $attribute['id']) . " — RiC-CM v{$version}"])

@section('content')
    @include('ahg-ric-model::partials._breadcrumb', ['version' => $version, 'trail' => [
        ['label' => 'Attributes', 'url' => route('reference.ric-cm.attributes.index', ['version' => $version])],
        ['label' => $attribute['name'] ?? $attribute['id']],
    ]])

    <h1 class="h3 mb-2">{{ $attribute['name'] ?? $attribute['id'] }} <code class="ric-id">{{ $attribute['id'] }}</code></h1>

    @if (!empty($attribute['definition']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Definition</h2>
            <p>{{ $attribute['definition'] }}</p>
        </section>
    @endif

    @if (!empty($attribute['scopeNotes']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Scope notes</h2>
            @foreach ($attribute['scopeNotes'] as $note)<p>{{ $note }}</p>@endforeach
        </section>
    @endif

    <section class="mb-4">
        <h2 class="h5">Declared on</h2>
        @if (empty($attribute['domainEntities']))
            <p class="text-muted small">No declared domain entities.</p>
        @else
            <ul>
                @foreach ($attribute['domainEntities'] as $e)
                    <li><a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $e['id']]) }}">{{ $e['name'] }}</a> <code class="ric-id">{{ $e['id'] }}</code></li>
                @endforeach
            </ul>
        @endif
    </section>

    @if (!empty($attribute['inheritedBy']))
        <section class="mb-4" x-data="{ open: false }">
            <h2 class="h5">
                Also applies to <span class="badge bg-secondary">{{ count($attribute['inheritedBy']) }}</span>
                <button type="button" class="btn btn-sm btn-link" @click="open = !open" x-text="open ? 'Hide' : 'Show'" :aria-expanded="open" aria-controls="inherited-by-panel"></button>
                <span class="ric-tag ric-tag-inherited small">inherited by</span>
            </h2>
            <div id="inherited-by-panel" x-show="open" x-cloak>
                <p class="text-muted small mb-2">Sub-classes that inherit this attribute. Click a subclass to see its full declared-plus-inherited view.</p>
                <ul>
                    @foreach ($attribute['inheritedBy'] as $d)
                        <li data-inherited-from="{{ $d['inheritedFrom']['id'] }}">
                            <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $d['id']]) }}">{{ $d['name'] }}</a>
                            <code class="ric-id">{{ $d['id'] }}</code>
                            <span class="ric-tag ric-tag-inherited small">via {{ $d['inheritedFrom']['name'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if (!empty($attribute['examples']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Examples</h2>
            <ul>@foreach ($attribute['examples'] as $ex)<li>{{ $ex }}</li>@endforeach</ul>
        </section>
    @endif
@endsection
