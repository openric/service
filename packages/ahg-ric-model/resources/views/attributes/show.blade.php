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
@endsection
