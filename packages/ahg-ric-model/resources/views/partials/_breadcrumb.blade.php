@php
    $trail = $trail ?? [];
@endphp
<nav aria-label="breadcrumb" class="small text-muted mb-3">
    <a href="{{ route('reference.ric-cm.index', ['version' => $version]) }}">Reference</a><span class="crumb-sep"></span>
    <span>RiC-CM v{{ $version }}</span>
    @foreach ($trail as $step)
        <span class="crumb-sep"></span>
        @if (is_array($step) && isset($step['url']))
            <a href="{{ $step['url'] }}">{{ $step['label'] }}</a>
        @else
            <span>{{ is_array($step) ? $step['label'] : $step }}</span>
        @endif
    @endforeach
</nav>
