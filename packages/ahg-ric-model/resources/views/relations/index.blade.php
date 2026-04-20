@extends('ahg-ric-model::partials._layout', ['pageTitle' => "Relations — RiC-CM v{$version}"])

@section('content')
    @include('ahg-ric-model::partials._breadcrumb', ['version' => $version, 'trail' => [['label' => 'Relations']]])

    <h1 class="h3 mb-3">Relations ({{ count($relations) }})</h1>

    <div x-data="ricListFilter" x-init="init($refs.table)">
        @include('ahg-ric-model::partials._search-filter', ['placeholder' => 'Filter by name, ID, domain, or range', 'totalLabel' => 'relations'])

        <div class="table-responsive">
            <table class="table table-sm align-middle" x-ref="table">
                <thead>
                    <tr>
                        <th style="width:8em;">ID</th>
                        <th>Name</th>
                        <th class="d-none d-md-table-cell">Domain</th>
                        <th class="d-none d-md-table-cell">Range</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($relations as $r)
                        @php $hay = strtolower(($r['id'] ?? '') . ' ' . ($r['name'] ?? '') . ' ' . ($r['definition'] ?? '') . ' ' . ($r['domain'] ?? '') . ' ' . ($r['range'] ?? '')); @endphp
                        <tr data-search="{{ $hay }}">
                            <td><code class="ric-id">{{ $r['id'] }}</code></td>
                            <td>
                                <a href="{{ route('reference.ric-cm.relations.show', ['version' => $version, 'id' => $r['id']]) }}">{{ $r['name'] ?? $r['id'] }}</a>
                                <div class="d-md-none small text-muted">
                                    @if (!empty($r['domain']))<code class="ric-id">{{ $r['domain'] }}</code>@endif
                                    @if (!empty($r['domain']) && !empty($r['range'])) → @endif
                                    @if (!empty($r['range']))<code class="ric-id">{{ $r['range'] }}</code>@endif
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">@if (!empty($r['domain']))<code class="ric-id">{{ $r['domain'] }}</code>@endif</td>
                            <td class="d-none d-md-table-cell">@if (!empty($r['range']))<code class="ric-id">{{ $r['range'] }}</code>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
