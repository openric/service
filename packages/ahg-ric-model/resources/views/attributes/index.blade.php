@extends('ahg-ric-model::partials._layout', ['pageTitle' => "Attributes — RiC-CM v{$version}"])

@section('content')
    @include('ahg-ric-model::partials._breadcrumb', ['version' => $version, 'trail' => [['label' => 'Attributes']]])

    <h1 class="h3 mb-3">Attributes ({{ count($attributes) }})</h1>

    <div x-data="ricListFilter" x-init="init($refs.table)">
        @include('ahg-ric-model::partials._search-filter', ['placeholder' => 'Filter by name, ID, or definition', 'totalLabel' => 'attributes'])

        <div class="table-responsive">
            <table class="table table-sm align-middle" x-ref="table">
                <thead>
                    <tr>
                        <th style="width:7em;">ID</th>
                        <th>Name</th>
                        <th class="d-none d-md-table-cell">Declared on</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attributes as $a)
                        @php $hay = strtolower(($a['id'] ?? '') . ' ' . ($a['name'] ?? '') . ' ' . ($a['definition'] ?? '') . ' ' . implode(' ', ($a['domain'] ?? []))); @endphp
                        <tr data-search="{{ $hay }}">
                            <td><code class="ric-id">{{ $a['id'] }}</code></td>
                            <td>
                                <a href="{{ route('reference.ric-cm.attributes.show', ['version' => $version, 'id' => $a['id']]) }}">{{ $a['name'] ?? $a['id'] }}</a>
                                <div class="d-md-none small text-muted">
                                    @foreach (($a['domain'] ?? []) as $eid)<code class="ric-id">{{ $eid }}</code>@if (!$loop->last), @endif @endforeach
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                @foreach (($a['domain'] ?? []) as $eid)
                                    <code class="ric-id">{{ $eid }}</code>@if (!$loop->last), @endif
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
