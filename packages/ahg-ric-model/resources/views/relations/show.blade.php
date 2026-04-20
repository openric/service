@extends('ahg-ric-model::partials._layout', ['pageTitle' => ($relation['name'] ?? $relation['id']) . " — RiC-CM v{$version}"])

@section('content')
    @include('ahg-ric-model::partials._breadcrumb', ['version' => $version, 'trail' => [
        ['label' => 'Relations', 'url' => route('reference.ric-cm.relations.index', ['version' => $version])],
        ['label' => $relation['name'] ?? $relation['id']],
    ]])

    <h1 class="h3 mb-2">{{ $relation['name'] ?? $relation['id'] }} <code class="ric-id">{{ $relation['id'] }}</code></h1>

    @if (!empty($relation['inverseRelation']))
        <p class="text-muted small mb-3">
            Inverse of
            <a href="{{ route('reference.ric-cm.relations.show', ['version' => $version, 'id' => $relation['inverseRelation']['id']]) }}">{{ $relation['inverseRelation']['name'] }}</a>
            <code class="ric-id">{{ $relation['inverseRelation']['id'] }}</code>
        </p>
    @endif

    @if (!empty($relation['definition']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Definition</h2>
            <p>{{ $relation['definition'] }}</p>
        </section>
    @endif

    @if (!empty($relation['scopeNotes']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Scope notes</h2>
            @foreach ($relation['scopeNotes'] as $note)<p>{{ $note }}</p>@endforeach
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

    @if (!empty($relation['examples']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Examples</h2>
            <ul>@foreach ($relation['examples'] as $ex)<li>{{ $ex }}</li>@endforeach</ul>
        </section>
    @endif

    @if (!empty($relation['broader']) || !empty($relation['narrower']))
        <section class="mb-4">
            <h2 class="h5">Hierarchy</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <h3 class="h6 text-uppercase text-muted">Broader</h3>
                    @if (empty($relation['broader']))
                        <p class="text-muted small">None</p>
                    @else
                        <ul>
                            @foreach ($relation['broader'] as $b)
                                <li><a href="{{ route('reference.ric-cm.relations.show', ['version' => $version, 'id' => $b['id']]) }}">{{ $b['name'] }}</a> <code class="ric-id">{{ $b['id'] }}</code></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="col-md-6">
                    <h3 class="h6 text-uppercase text-muted">Narrower</h3>
                    @if (empty($relation['narrower']))
                        <p class="text-muted small">None</p>
                    @else
                        <ul>
                            @foreach ($relation['narrower'] as $n)
                                <li><a href="{{ route('reference.ric-cm.relations.show', ['version' => $version, 'id' => $n['id']]) }}">{{ $n['name'] }}</a> <code class="ric-id">{{ $n['id'] }}</code></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if (!empty($relation['domainDescendants']) || !empty($relation['rangeDescendants']))
        <section class="mb-4" x-data="{ open: false }">
            <h2 class="h5">
                Subclasses covered <button type="button" class="btn btn-sm btn-link" @click="open = !open" x-text="open ? 'Hide' : 'Show'" :aria-expanded="open" aria-controls="subclasses-covered"></button>
                <span class="ric-tag ric-tag-browsing small">browsing aid</span>
            </h2>
            <div id="subclasses-covered" x-show="open" x-cloak>
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
