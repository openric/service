@extends('ahg-ric-model::partials._layout', ['pageTitle' => "Relation attributes — RiC-CM v{$version}"])

@section('content')
    @include('ahg-ric-model::partials._breadcrumb', ['version' => $version, 'trail' => [['label' => 'Relation attributes']]])

    <h1 class="h3 mb-3">Relation attributes ({{ count($relationAttributes) }})</h1>

    <p class="text-muted small mb-3">Characteristics that qualify a relation — its certainty, validity dates, role, source.</p>

    <div x-data="ricListFilter" x-init="init($refs.table)">
        @include('ahg-ric-model::partials._search-filter', ['placeholder' => 'Filter by name, ID, or definition', 'totalLabel' => 'relation attributes'])

        <div class="table-responsive">
            <table class="table table-sm align-middle" x-ref="table">
                <thead>
                    <tr>
                        <th style="width:8em;">ID</th>
                        <th>Name</th>
                        <th class="d-none d-md-table-cell">Definition</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($relationAttributes as $ra)
                        @php $hay = strtolower(($ra['id'] ?? '') . ' ' . ($ra['name'] ?? '') . ' ' . ($ra['definition'] ?? '')); @endphp
                        <tr data-search="{{ $hay }}">
                            <td><code class="ric-id">{{ $ra['id'] }}</code></td>
                            <td>
                                <a href="{{ route('reference.ric-cm.relation-attributes.show', ['version' => $version, 'id' => $ra['id']]) }}">{{ $ra['name'] ?? $ra['id'] }}</a>
                                <div class="d-md-none small text-muted">{{ \Illuminate\Support\Str::limit($ra['definition'] ?? '', 120) }}</div>
                            </td>
                            <td class="text-muted d-none d-md-table-cell">{{ \Illuminate\Support\Str::limit($ra['definition'] ?? '', 160) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
