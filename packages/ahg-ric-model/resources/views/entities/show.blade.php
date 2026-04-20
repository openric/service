@extends('ahg-ric-model::partials._layout', ['pageTitle' => ($entity['name'] ?? $entity['id']) . " — RiC-CM v{$version}"])

@section('content')
    @include('ahg-ric-model::partials._breadcrumb', ['version' => $version, 'trail' => [
        ['label' => 'Entities', 'url' => route('reference.ric-cm.entities.index', ['version' => $version])],
        ['label' => $entity['name'] ?? $entity['id']],
    ]])

    <h1 class="h3 mb-2">{{ $entity['name'] ?? $entity['id'] }} <code class="ric-id">{{ $entity['id'] }}</code></h1>

    @if (!empty($ancestors))
        <p class="text-muted small mb-3">
            Ancestors:
            @foreach (array_reverse($ancestors) as $a)
                <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $a['id']]) }}">{{ $a['name'] }}</a>
                @if (!$loop->last) › @endif
            @endforeach
            › <strong>{{ $entity['name'] ?? $entity['id'] }}</strong>
        </p>
    @endif

    @if (!empty($entity['definition']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Definition</h2>
            <p>{{ $entity['definition'] }}</p>
        </section>
    @endif

    @if (!empty($entity['scopeNotes']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Scope notes</h2>
            @foreach ($entity['scopeNotes'] as $note)
                <p>{{ $note }}</p>
            @endforeach
        </section>
    @endif

    @if (!empty($entity['examples']))
        <section class="mb-4">
            <h2 class="h6 text-uppercase text-muted">Examples</h2>
            <ul>
                @foreach ($entity['examples'] as $ex)
                    <li>{{ $ex }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section id="declared-attributes" class="mb-4">
        <h2 class="h5">Declared attributes <span class="badge bg-secondary">{{ count($declaredAttributes) }}</span></h2>
        @if (count($declaredAttributes) === 0)
            <p class="text-muted small">No attributes declared directly on this entity.</p>
        @else
            <table class="table table-sm">
                <thead><tr><th style="width:7em;">ID</th><th>Name</th><th>Definition</th></tr></thead>
                <tbody>
                    @foreach ($declaredAttributes as $a)
                        <tr>
                            <td><code class="ric-id">{{ $a['id'] }}</code></td>
                            <td><a href="{{ route('reference.ric-cm.attributes.show', ['version' => $version, 'id' => $a['id']]) }}">{{ $a['name'] ?? $a['id'] }}</a></td>
                            <td class="text-muted">{{ \Illuminate\Support\Str::limit($a['definition'] ?? '', 160) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section id="declared-relations" class="mb-4">
        <h2 class="h5">Declared relations <span class="badge bg-secondary">{{ count($declaredRelations) }}</span></h2>
        @if (count($declaredRelations) === 0)
            <p class="text-muted small">No relations declared directly on this entity.</p>
        @else
            <table class="table table-sm">
                <thead><tr><th style="width:7em;">ID</th><th>Name</th><th>Range</th></tr></thead>
                <tbody>
                    @foreach ($declaredRelations as $r)
                        <tr>
                            <td><code class="ric-id">{{ $r['id'] }}</code></td>
                            <td><a href="{{ route('reference.ric-cm.relations.show', ['version' => $version, 'id' => $r['id']]) }}">{{ $r['name'] ?? $r['id'] }}</a></td>
                            <td>@if (!empty($r['range']))<code class="ric-id">{{ $r['range'] }}</code>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="mb-4" x-data="{ open: false }">
        <h2 class="h5">
            Inherited attributes <span class="badge bg-secondary">{{ count($inheritedAttributes) }}</span>
            @if (count($inheritedAttributes) > 0)
                <button type="button" class="btn btn-sm btn-link" @click="open = !open" x-text="open ? 'Hide' : 'Show'" :aria-expanded="open" aria-controls="inherited-attributes-panel"></button>
            @endif
        </h2>
        @if (count($inheritedAttributes) > 0)
            <div id="inherited-attributes-panel" x-show="open" x-cloak>
                <table class="table table-sm">
                    <thead><tr><th style="width:7em;">ID</th><th>Name</th><th>Inherited from</th></tr></thead>
                    <tbody>
                        @foreach ($inheritedAttributes as $a)
                            <tr data-inherited-from="{{ $a['inheritedFrom']['id'] }}">
                                <td><code class="ric-id">{{ $a['id'] }}</code></td>
                                <td><a href="{{ route('reference.ric-cm.attributes.show', ['version' => $version, 'id' => $a['id']]) }}">{{ $a['name'] ?? $a['id'] }}</a></td>
                                <td>
                                    <span class="ric-tag ric-tag-inherited">
                                        from <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $a['inheritedFrom']['id']]) }}#declared-attributes" class="text-reset">{{ $a['inheritedFrom']['name'] }}</a>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="mb-4" x-data="{ open: false }">
        <h2 class="h5">
            Inherited relations <span class="badge bg-secondary">{{ count($inheritedRelations) }}</span>
            @if (count($inheritedRelations) > 0)
                <button type="button" class="btn btn-sm btn-link" @click="open = !open" x-text="open ? 'Hide' : 'Show'" :aria-expanded="open" aria-controls="inherited-relations-panel"></button>
            @endif
        </h2>
        @if (count($inheritedRelations) > 0)
            <div id="inherited-relations-panel" x-show="open" x-cloak>
                <table class="table table-sm">
                    <thead><tr><th style="width:7em;">ID</th><th>Name</th><th>Range</th><th>Inherited from</th></tr></thead>
                    <tbody>
                        @foreach ($inheritedRelations as $r)
                            <tr data-inherited-from="{{ $r['inheritedFrom']['id'] }}">
                                <td><code class="ric-id">{{ $r['id'] }}</code></td>
                                <td><a href="{{ route('reference.ric-cm.relations.show', ['version' => $version, 'id' => $r['id']]) }}">{{ $r['name'] ?? $r['id'] }}</a></td>
                                <td>@if (!empty($r['range']))<code class="ric-id">{{ $r['range'] }}</code>@endif</td>
                                <td>
                                    <span class="ric-tag ric-tag-inherited">
                                        from <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $r['inheritedFrom']['id']]) }}#declared-relations" class="text-reset">{{ $r['inheritedFrom']['name'] }}</a>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if (!empty($descendants))
        <section class="mb-4" x-data="{ open: false }">
            <h2 class="h5">
                Subclasses <span class="badge bg-secondary">{{ count($descendants) }}</span>
                <button type="button" class="btn btn-sm btn-link" @click="open = !open" x-text="open ? 'Hide' : 'Show'" :aria-expanded="open" aria-controls="subclasses-panel"></button>
                <span class="ric-tag ric-tag-browsing small">browsing aid</span>
            </h2>
            <div id="subclasses-panel" x-show="open" x-cloak>
                <p class="text-muted small mb-2">These are navigation shortcuts. The attributes and relations listed above belong to <strong>{{ $entity['name'] ?? $entity['id'] }}</strong>; each subclass may declare or inherit more of its own.</p>
                <ul>
                    @foreach ($descendants as $d)
                        <li><a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $d['id']]) }}">{{ $d['name'] }}</a> <code class="ric-id">{{ $d['id'] }}</code></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
