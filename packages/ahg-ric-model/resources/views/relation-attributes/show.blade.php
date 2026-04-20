@extends('ahg-ric-model::partials._layout', ['pageTitle' => ($relationAttribute['name'] ?? $relationAttribute['id']) . " — RiC-CM v{$version}"])

@section('content')
    @include('ahg-ric-model::partials._breadcrumb', ['version' => $version, 'trail' => [
        ['label' => 'Relation attributes', 'url' => route('reference.ric-cm.relation-attributes.index', ['version' => $version])],
        ['label' => $relationAttribute['name'] ?? $relationAttribute['id']],
    ]])

    <h1 class="h3 mb-2">{{ $relationAttribute['name'] ?? $relationAttribute['id'] }} <code class="ric-id">{{ $relationAttribute['id'] }}</code></h1>

    @if (!empty($relationAttribute['definition']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Definition</h2>
            <p>{{ $relationAttribute['definition'] }}</p>
        </section>
    @endif
@endsection
