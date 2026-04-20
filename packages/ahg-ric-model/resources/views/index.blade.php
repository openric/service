@extends('ahg-ric-model::partials._layout', ['pageTitle' => "RiC-CM v{$version} Reference"])

@section('content')
    <h1 class="h3 mb-4">RiC-CM v{{ $version }}</h1>

    <p class="text-muted mb-4">Browse the Records in Contexts Conceptual Model — 19 entities, 42 attributes, and their relations — sourced live from RiC-O v1.1 via SPARQL.</p>

    <div class="row g-3 mb-4">
        @php $cards = [
            ['label' => 'Entities',            'count' => $counts['entities'],            'route' => 'entities.index'],
            ['label' => 'Attributes',          'count' => $counts['attributes'],          'route' => 'attributes.index'],
            ['label' => 'Relations',           'count' => $counts['relations'],           'route' => 'relations.index'],
            ['label' => 'Relation attributes', 'count' => $counts['relationAttributes'],  'route' => 'relation-attributes.index'],
        ]; @endphp

        @foreach ($cards as $c)
            <div class="col-6 col-md-3">
                <a class="text-decoration-none" href="{{ route('reference.ric-cm.' . $c['route'], ['version' => $version]) }}">
                    <div class="card subtle-card h-100">
                        <div class="card-body">
                            <div class="h2 mb-1">{{ $c['count'] }}</div>
                            <div class="text-muted">{{ $c['label'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <h2 class="h5 mb-3">Class hierarchy</h2>
    <div class="card subtle-card">
        <div class="card-body">
            @include('ahg-ric-model::partials._hierarchy', ['nodes' => $hierarchy, 'version' => $version])
        </div>
    </div>

    @if (count($availableVersions) > 1)
        <p class="text-muted small mt-4">Other versions:
            @foreach ($availableVersions as $v)
                @if ($v === $version)<strong>v{{ $v }}</strong>@else<a href="{{ route('reference.ric-cm.index', ['version' => $v]) }}">v{{ $v }}</a>@endif
                @if (!$loop->last), @endif
            @endforeach
        </p>
    @endif
@endsection
