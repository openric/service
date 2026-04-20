@extends('ahg-ric-model::partials._layout', ['pageTitle' => "Entities — RiC-CM v{$version}"])

@section('content')
    @include('ahg-ric-model::partials._breadcrumb', ['version' => $version, 'trail' => [['label' => 'Entities']]])

    <h1 class="h3 mb-3">Entities ({{ count($entities) }})</h1>

    <div x-data="ricListFilter" x-init="init($refs.table)">
        @include('ahg-ric-model::partials._search-filter', ['placeholder' => 'Filter by name, ID, or definition', 'totalLabel' => 'entities'])

        <div class="table-responsive">
            <table class="table table-sm align-middle" x-ref="table">
                <thead>
                    <tr>
                        <th style="width:7em;">ID</th>
                        <th>Name</th>
                        <th class="d-none d-md-table-cell">Definition</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entities as $e)
                        @php $hay = strtolower(($e['id'] ?? '') . ' ' . ($e['name'] ?? '') . ' ' . ($e['definition'] ?? '')); @endphp
                        <tr data-search="{{ $hay }}">
                            <td><code class="ric-id">{{ $e['id'] }}</code></td>
                            <td>
                                <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $e['id']]) }}">{{ $e['name'] ?? $e['id'] }}</a>
                                <div class="d-md-none small text-muted">{{ \Illuminate\Support\Str::limit($e['definition'] ?? '', 120) }}</div>
                            </td>
                            <td class="text-muted d-none d-md-table-cell">{{ \Illuminate\Support\Str::limit($e['definition'] ?? '', 140) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
