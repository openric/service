<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Content negotiation by profile (W3C Content Negotiation by Profile, the
 * http and qsa realisations): a client asks for a profile with the
 * Accept-Profile header or the _profile query parameter, and the answer says
 * which one it got in Content-Profile and a Link rel="profile". _profile=alt
 * lists the profiles with the ontology, the SHACL shapes and the guidance for
 * each (openric/service#13).
 *
 * Two profiles: OpenRiC (RiC-O 1.1 with the openricx: extension, what the
 * service has always sent, and the default) and plain RiC-O 1.1, which drops
 * every openricx: term from a JSON-LD answer for a client that reads RiC-O
 * only. Turtle and RDF/XML are sent in the default profile, and say so.
 */

namespace AhgRic\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileNegotiation
{
    public const OPENRIC = 'https://openric.org/ns/ext/v1';
    public const RICO = 'https://www.ica.org/standards/RiC/ontology';

    /** token => description; the first is the default. */
    public const PROFILES = [
        'openric' => [
            'uri' => self::OPENRIC,
            'label' => 'OpenRiC: RiC-O 1.1 with the OpenRiC extension (openricx:)',
            'ontology' => 'https://openric.org/ns/ext/v1.ttl',
            'shapes' => 'https://openric.org/shapes/openric.shacl.ttl',
            'guidance' => 'https://openric.org/spec/mapping.html',
        ],
        'rico' => [
            'uri' => self::RICO,
            'label' => 'RiC-O 1.1 only: every openricx: term is left out of JSON-LD',
            'ontology' => 'https://www.ica.org/standards/RiC/ontology',
            'shapes' => null,
            'guidance' => 'https://www.ica.org/standards/RiC/ontology',
        ],
    ];

    public function handle(Request $request, Closure $next)
    {
        $asked = (string) $request->query('_profile', '');
        if ($asked === 'alt') {
            return $this->withLinks(response()->json($this->list($request), 200), 'openric');
        }
        $token = $this->choose($asked, (string) $request->header('Accept-Profile', ''));
        $response = $next($request);

        // Only a JSON-LD answer can be cut down to plain RiC-O; anything else
        // goes out in the default profile and says so.
        if ($token === 'rico' && !($response instanceof JsonResponse && $response->isSuccessful())) {
            $token = 'openric';
        }
        if ($token === 'rico') {
            $data = $response->getData(true);
            if (is_array($data)) {
                $response->setData(self::stripExtension($data));
            }
        }
        return $this->withLinks($response, $token);
    }

    /** The profile asked for: _profile (token or URI) wins, then Accept-Profile by q, else the default. */
    public function choose(string $qsa, string $header): string
    {
        if ($qsa !== '') {
            return $this->token(trim($qsa, '<> ')) ?? 'openric';
        }
        $best = null;
        $bestQ = 0.0;
        foreach (array_filter(array_map('trim', explode(',', $header))) as $part) {
            $bits = array_map('trim', explode(';', $part));
            $uri = trim(array_shift($bits), '<> ');
            $q = 1.0;
            foreach ($bits as $b) {
                if (preg_match('/^q=([0-9.]+)$/', $b, $m)) {
                    $q = (float) $m[1];
                }
            }
            $t = $this->token($uri);
            if ($t !== null && $q > $bestQ) {
                [$best, $bestQ] = [$t, $q];
            }
        }
        return $best ?? 'openric';
    }

    private function token(string $v): ?string
    {
        foreach (self::PROFILES as $t => $p) {
            if ($v === $t || rtrim($v, '#/') === $p['uri']) {
                return $t;
            }
        }
        return null;
    }

    /** Every openricx: key, @type and prefix removed; RiC-O, schema and the rest untouched. */
    public static function stripExtension(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && (str_starts_with($k, 'openricx:') || str_starts_with($k, self::OPENRIC . '#'))) {
                continue;
            }
            if ($k === '@context' && is_array($v)) {
                unset($v['openricx']);
            }
            if ($k === '@type') {
                $types = array_values(array_filter((array) $v, fn ($t) => !(is_string($t) && str_starts_with($t, 'openricx:'))));
                if (!$types) {
                    continue;
                }
                $v = is_array($v) ? $types : $types[0];
            } elseif (is_array($v)) {
                $v = self::stripExtension($v);
            }
            $out[$k] = $v;
        }
        return $out;
    }

    private function list(Request $request): array
    {
        $profiles = [];
        foreach (self::PROFILES as $t => $p) {
            $profiles[] = ['token' => $t, 'default' => $t === 'openric'] + $p;
        }
        return [
            'resource' => $request->url(),
            'how' => 'Ask with the Accept-Profile header (Accept-Profile: <uri>) or ?_profile=<token or uri>. The answer names its profile in Content-Profile and a Link rel="profile".',
            'profiles' => $profiles,
        ];
    }

    private function withLinks($response, string $token)
    {
        $links = [];
        foreach (self::PROFILES as $t => $p) {
            $links[] = $t === $token
                ? sprintf('<%s>; rel="profile"', $p['uri'])
                : sprintf('<%s?_profile=%s>; rel="alternate"; formats="application/ld+json"; profile="%s"', url()->current(), $t, $p['uri']);
        }
        $response->headers->set('Content-Profile', '<' . self::PROFILES[$token]['uri'] . '>');
        $response->headers->set('Link', implode(', ', $links), false);
        $response->headers->set('Vary', 'Accept-Profile', false);
        return $response;
    }
}
