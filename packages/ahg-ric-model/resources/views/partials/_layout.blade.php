<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle ?? 'RiC-CM Reference' }} — OpenRiC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        body { padding-top: 3.5rem; }
        code.ric-id { font-family: var(--bs-font-monospace); font-size: 0.9em; color: #6f42c1; }
        .ric-tag { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 0.2rem; font-size: 0.78rem;
                   background: #f1f3f5; color: #495057; }
        .ric-tag-inherited { background: #e7f5ff; color: #1c7ed6; }
        .ric-tag-browsing  { background: #fff4e6; color: #f76707; }
        .crumb-sep::before { content: "›"; margin: 0 0.4rem; color: #adb5bd; }
        .subtle-card { border: 1px solid #e9ecef; }
        .attribution { font-size: 0.78rem; color: #868e96; border-top: 1px solid #e9ecef; margin-top: 3rem; padding-top: 1rem; }

        /* Keyboard-visible focus indicator — WCAG 2.4.7. */
        a:focus-visible, button:focus-visible, input:focus-visible, [tabindex]:focus-visible {
            outline: 2px solid #1c7ed6;
            outline-offset: 2px;
        }

        /* Skip-to-content link — visible only when focused. */
        .skip-link { position: absolute; top: -40px; left: 0; padding: 0.5rem 1rem; background: #1c7ed6; color: #fff;
                     z-index: 2000; text-decoration: none; }
        .skip-link:focus { top: 0; color: #fff; }

        /* Print: drop navigation and filters; auto-expand collapsed sections;
           show full content including inherited panels. */
        @media print {
            .navbar, .skip-link, [role="search"], button.btn-link, button.btn-outline-secondary { display: none !important; }
            body { padding-top: 0; }
            main.container { max-width: none; padding: 0; }
            [x-cloak] { display: revert !important; }
            a { color: inherit; text-decoration: none; }
            a[href]::after { content: " (" attr(href) ")"; font-size: 0.75em; color: #6c757d; word-break: break-all; }
            .attribution { page-break-inside: avoid; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<a href="#main-content" class="skip-link">Skip to content</a>

<nav class="navbar fixed-top navbar-expand-md bg-body-tertiary border-bottom">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('reference.ric-cm.index', ['version' => $version ?? config('ahg-ric-model.versions.latest')]) }}">
            OpenRiC &middot; <span class="text-muted">RiC-CM Reference</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('reference.ric-cm.entities.index', ['version' => $version]) }}">Entities</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('reference.ric-cm.attributes.index', ['version' => $version]) }}">Attributes</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('reference.ric-cm.relations.index', ['version' => $version]) }}">Relations</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('reference.ric-cm.relation-attributes.index', ['version' => $version]) }}">Relation Attributes</a></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-md-center">
                <li class="nav-item"><a class="nav-link" href="https://openric.org/guides/getting-started.html" target="_blank" rel="noopener">Start here <span aria-hidden="true">↗</span></a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">openric.org</a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="https://openric.org/spec/" target="_blank" rel="noopener">Spec ↗</a></li>
                        <li><a class="dropdown-item" href="https://openric.org/guides/" target="_blank" rel="noopener">Guides ↗</a></li>
                        <li><a class="dropdown-item" href="https://openric.org/faq.html" target="_blank" rel="noopener">FAQ ↗</a></li>
                        <li><a class="dropdown-item" href="https://openric.org/architecture.html" target="_blank" rel="noopener">Architecture ↗</a></li>
                        <li><a class="dropdown-item" href="https://openric.org/conformance/" target="_blank" rel="noopener">Conformance ↗</a></li>
                        <li><a class="dropdown-item" href="https://openric.org/governance.html" target="_blank" rel="noopener">Governance ↗</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="https://github.com/openric/spec/discussions" target="_blank" rel="noopener">Discussions ↗</a></li>
                        <li><a class="dropdown-item" href="https://github.com/openric/spec" target="_blank" rel="noopener">GitHub ↗</a></li>
                    </ul>
                </li>
                <li class="nav-item"><span class="navbar-text small text-muted ms-md-2">RiC-CM v{{ $version }}</span></li>
            </ul>
        </div>
    </div>
</nav>

<main id="main-content" class="container py-3" tabindex="-1">
    @yield('content')

    @isset($attribution)
    <p class="attribution">
        {{ $attribution['ontology_credit'] }}
        <a href="{{ $attribution['ontology_url'] }}" target="_blank" rel="noopener">Source</a> ·
        <a href="{{ $attribution['license_url'] }}" target="_blank" rel="noopener">CC BY 4.0</a>
    </p>
    @endisset
</main>

</body>
</html>
