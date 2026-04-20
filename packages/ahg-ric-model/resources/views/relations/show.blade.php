@extends('ahg-ric-model::partials._layout', ['pageTitle' => ($relation['name'] ?? $relation['id']) . " — RiC-CM v{$version}"])

@section('content')
    @include('ahg-ric-model::partials._breadcrumb', ['version' => $version, 'trail' => [
        ['label' => 'Relations', 'url' => route('reference.ric-cm.relations.index', ['version' => $version])],
        ['label' => $relation['name'] ?? $relation['id']],
    ]])

    <h1 class="h3 mb-2">{{ $relation['name'] ?? $relation['id'] }} <code class="ric-id">{{ $relation['id'] }}</code></h1>

    @if (!empty($relation['inverseOf']))
        <p class="text-muted small mb-3">Inverse of <a href="{{ route('reference.ric-cm.relations.show', ['version' => $version, 'id' => $relation['inverseOf']]) }}"><code class="ric-id">{{ $relation['inverseOf'] }}</code></a></p>
    @endif

    @if (!empty($relation['definition']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Definition</h2>
            <p>{{ $relation['definition'] }}</p>
        </section>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <h2 class="h5">Declared domain</h2>
            @if (!empty($relation['domainEntity']))
                <p><a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $relation['domainEntity']['id']]) }}">{{ $relation['domainEntity']['name'] }}</a> <code class="ric-id">{{ $relation['domainEntity']['id'] }}</code></p>
            @else
                <p class="text-muted small">Unspecified</p>
            @endif
        </div>
        <div class="col-md-6">
            <h2 class="h5">Declared range</h2>
            @if (!empty($relation['rangeEntity']))
                <p><a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $relation['rangeEntity']['id']]) }}">{{ $relation['rangeEntity']['name'] }}</a> <code class="ric-id">{{ $relation['rangeEntity']['id'] }}</code></p>
            @else
                <p class="text-muted small">Unspecified</p>
            @endif
        </div>
    </div>

    @if (!empty($relation['domainDescendants']) || !empty($relation['rangeDescendants']))
        <section class="mb-4" x-data="{ open: false }">
            <h2 class="h5">
                Subclasses covered <button type="button" class="btn btn-sm btn-link" @click="open = !open" x-text="open ? 'Hide' : 'Show'" :aria-expanded="open"></button>
                <span class="ric-tag ric-tag-browsing small">browsing aid</span>
            </h2>
            <div x-show="open" x-cloak>
                <p class="text-muted small mb-2">These are navigation shortcuts. The relation's declared domain and range are the single entries above; the subclass lists below are provided for quick jumping only, not as part of the relation's semantic definition.</p>
                <div class="row">
                    <div class="col-md-6">
                        <h3 class="h6">Domain subclasses ({{ count($relation['domainDescendants']) }})</h3>
                        <ul>
                            @foreach ($relation['domainDescendants'] as $d)
                                <li><a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $d['id']]) }}">{{ $d['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6">Range subclasses ({{ count($relation['rangeDescendants']) }})</h3>
                        <ul>
                            @foreach ($relation['rangeDescendants'] as $d)
                                <li><a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $d['id']]) }}">{{ $d['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    @endif
@endsection
