{{--
  Client-side filter for a server-rendered list.

  Usage (wrap the table in this Alpine component):

      <div x-data="ricListFilter" x-init="init($refs.table)">
        @include('ahg-ric-model::partials._search-filter', ['placeholder' => 'Filter entities'])
        <table x-ref="table">
          <tbody>
            <tr data-search="{{ strtolower($row['id'].' '.$row['name'].' '.$row['def']) }}"> ... </tr>
            ...

  The filter is progressively enhanced — without JS, all rows render
  and the input is inert. With JS, rows hide/show on keystroke; the
  visible count updates live; an empty-state block appears if the
  filter matches nothing.
--}}
@php
    $placeholder = $placeholder ?? 'Filter…';
    $totalLabel  = $totalLabel  ?? 'items';
@endphp

<script>
    if (!window.ricListFilterDefined) {
        window.ricListFilterDefined = true;
        document.addEventListener('alpine:init', () => {
            Alpine.data('ricListFilter', () => ({
                q: '',
                total: 0,
                visible: 0,
                rows: [],
                init(tableEl) {
                    this.rows = Array.from(tableEl.querySelectorAll('tbody > tr[data-search]'));
                    this.total = this.rows.length;
                    this.visible = this.total;
                    this.$watch('q', () => this.apply());
                },
                apply() {
                    const needle = this.q.trim().toLowerCase();
                    let visible = 0;
                    for (const row of this.rows) {
                        const show = needle === '' || (row.dataset.search || '').includes(needle);
                        row.style.display = show ? '' : 'none';
                        if (show) visible++;
                    }
                    this.visible = visible;
                },
                clear() {
                    this.q = '';
                    this.$refs.input?.focus();
                },
            }));
        });
    }
</script>

<div class="mb-3" role="search">
    <div class="input-group input-group-sm">
        <span class="input-group-text" aria-hidden="true">Filter</span>
        <input
            type="search"
            x-ref="input"
            x-model.debounce.150ms="q"
            class="form-control"
            placeholder="{{ $placeholder }}"
            aria-label="{{ $placeholder }}"
            autocomplete="off">
        <button
            type="button"
            class="btn btn-outline-secondary"
            @click="clear()"
            x-show="q.length > 0"
            x-cloak
            aria-label="Clear filter">×</button>
    </div>
    <p class="small text-muted mt-1 mb-0" x-cloak>
        <span x-show="q === ''">{{ $totalLabel }}: <span x-text="total"></span></span>
        <span x-show="q !== ''"><span x-text="visible"></span> of <span x-text="total"></span> {{ $totalLabel }} shown</span>
    </p>
</div>

{{-- Empty-state injected below the table via Alpine, if the filter matches nothing. --}}
<div class="alert alert-light border small" x-show="q !== '' && visible === 0" x-cloak role="status">
    No {{ $totalLabel }} match <strong x-text="q"></strong>. <button type="button" class="btn btn-link btn-sm p-0" @click="clear()">Clear filter</button>
</div>
