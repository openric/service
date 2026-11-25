<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Export-Only: one triple set in the three formats (openric/service#19). The
 * Turtle and RDF/XML a record's export gives must hold the same triples as its
 * JSON-LD, nested blank nodes and datatypes included. The graphs are compared
 * with rdflib, as the conformance probe does; without python3 and rdflib the
 * comparison is skipped, unless OPENRIC_REQUIRE_RDFLIB is set, as in CI.
 */

namespace Tests\Unit;

use AhgRic\Support\JsonLdConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JsonLdConverterTest extends TestCase
{
    private const COMPARE = <<<'PY'
import sys, rdflib
from rdflib.compare import to_isomorphic
j = to_isomorphic(rdflib.Graph().parse(sys.argv[1], format='json-ld'))
t = to_isomorphic(rdflib.Graph().parse(sys.argv[2], format='turtle'))
r = to_isomorphic(rdflib.Graph().parse(sys.argv[3], format='xml'))
print(len(j), len(t), len(r), 'same' if j == t and j == r else 'different')
PY;

    public static function documents(): array
    {
        return [
            'the record of the issue: an instantiation and two extents' => ['title-of-object.jsonld', 16],
            'languages, an embedded node, numbers, booleans, two types' => ['edge-cases.jsonld', 22],
        ];
    }

    #[DataProvider('documents')]
    public function test_turtle_and_rdf_xml_hold_the_json_ld_triples(string $fixture, int $triples): void
    {
        $python = $this->rdflib();
        $jsonld = __DIR__ . '/../fixtures/export/' . $fixture;
        $doc = json_decode(file_get_contents($jsonld), true);
        $ttl = tempnam(sys_get_temp_dir(), 'ttl');
        $rdf = tempnam(sys_get_temp_dir(), 'rdf');
        file_put_contents($ttl, JsonLdConverter::toTurtle($doc));
        file_put_contents($rdf, JsonLdConverter::toRdfXml($doc));

        $out = trim((string) shell_exec(sprintf('%s -c %s %s %s %s 2>&1',
            $python, escapeshellarg(self::COMPARE), escapeshellarg($jsonld), escapeshellarg($ttl), escapeshellarg($rdf))));
        unlink($ttl);
        unlink($rdf);

        $this->assertSame("{$triples} {$triples} {$triples} same", $out);
    }

    public function test_a_nested_blank_node_keeps_its_properties(): void
    {
        $doc = ['@context' => ['rico' => 'https://www.ica.org/standards/RiC/ontology#'], '@id' => 'https://example.org/r',
            'rico:hasExtent' => ['@type' => 'rico:Extent', 'rico:quantity' => 75504986, 'rico:hasExtentType' => 'bytes']];

        $ttl = JsonLdConverter::toTurtle($doc);
        $this->assertStringContainsString('rico:quantity 75504986', $ttl);
        $this->assertStringContainsString('rico:hasExtentType "bytes"', $ttl);

        $rdf = JsonLdConverter::toRdfXml($doc);
        $this->assertStringContainsString('<rico:Extent>', $rdf);
        $this->assertStringContainsString('<rico:quantity rdf:datatype="http://www.w3.org/2001/XMLSchema#integer">75504986</rico:quantity>', $rdf);
    }

    private function rdflib(): string
    {
        $python = getenv('OPENRIC_PYTHON') ?: 'python3';
        $ok = trim((string) shell_exec(escapeshellcmd($python) . " -c 'import rdflib; print(1)' 2>/dev/null")) === '1';
        if (!$ok) {
            if (getenv('OPENRIC_REQUIRE_RDFLIB')) {
                $this->fail('python3 with rdflib is required to compare the three formats');
            }
            $this->markTestSkipped('python3 with rdflib is not installed');
        }
        return $python;
    }
}
