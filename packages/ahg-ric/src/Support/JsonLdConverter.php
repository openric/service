<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Convert an OpenRiC JSON-LD graph (the shape returned by
 * RicSerializationService) into Turtle or RDF/XML. We avoid pulling in a
 * full RDF library (EasyRdf / hardf) and hand-roll a narrow converter
 * that handles the subset of JSON-LD the serializer actually produces:
 *
 *   - A top-level @graph array of typed nodes, OR
 *   - A single typed node
 *   - @context mapping prefixes to IRIs
 *   - @id as the subject URI
 *   - @type as rdf:type
 *   - rico:* / openric:* predicates with scalar or array values
 *   - Values that are strings, numbers, booleans, or {@id, @type?, @value?} objects
 *
 * Nested objects are written out in full, so the three formats carry one
 * triple set (Export-Only; openric/service#19): an object without @id is a
 * blank node with its own properties, and an embedded node with an @id and
 * properties of its own is written as a node of its own. Integers, decimals
 * and booleans carry the datatypes JSON-LD gives them.
 *
 * Known limitations:
 *   - Language tags on literals are preserved only via @language objects.
 *   - Context term definitions (a term mapped to an object) are not read;
 *     only prefix entries are.
 */

namespace AhgRic\Support;

class JsonLdConverter
{
    /** Convert a JSON-LD document to Turtle. */
    public static function toTurtle(array $doc): string
    {
        [$context, $nodes] = self::unpack($doc);

        $prefixes = [];
        foreach ($context as $k => $v) {
            // Skip malformed entries — a prefix must itself be a short name,
            // not a full IRI (some serializers put self-mapped IRIs here).
            if (!is_string($v)) continue;
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', (string) $k)) continue;
            $prefixes[$k] = $v;
        }
        // Always emit the standards we use.
        $prefixes += [
            'rdf'  => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'xsd'  => 'http://www.w3.org/2001/XMLSchema#',
            'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            'openric' => 'https://openric.org/ns/v1#',
        ];
        ksort($prefixes);

        $out = '';
        foreach ($prefixes as $p => $iri) {
            $out .= "@prefix {$p}: <{$iri}> .\n";
        }
        $out .= "\n";

        $bnCounter = 0;
        $queue = [];
        $seen = [];
        foreach ($nodes as $node) {
            if (isset($node['@id'])) $seen[$node['@id']] = true;
        }
        while ($nodes) {
            $node = array_shift($nodes);
            $out .= self::nodeToTurtle($node, $prefixes, $bnCounter, $queue) . "\n";
            // Embedded nodes with properties of their own follow, each once.
            foreach ($queue as $embedded) {
                if (isset($seen[$embedded['@id']])) continue;
                $seen[$embedded['@id']] = true;
                $nodes[] = $embedded;
            }
            $queue = [];
        }
        return $out;
    }

    /** Convert a JSON-LD document to RDF/XML. */
    public static function toRdfXml(array $doc): string
    {
        [$context, $nodes] = self::unpack($doc);

        $nsMap = [];
        foreach ($context as $k => $v) {
            if (!is_string($v)) continue;
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', (string) $k)) continue;
            $nsMap[$k] = $v;
        }
        $nsMap += [
            'rdf'  => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            'openric' => 'https://openric.org/ns/v1#',
        ];

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rdf:RDF';
        foreach ($nsMap as $p => $iri) {
            $xml .= ' xmlns:' . $p . '="' . htmlspecialchars($iri, ENT_XML1) . '"';
        }
        $xml .= ">\n";

        foreach ($nodes as $node) {
            $xml .= self::nodeToRdfXml($node, $nsMap);
        }
        $xml .= '</rdf:RDF>' . "\n";
        return $xml;
    }

    // ---- shared --------------------------------------------------

    private static function unpack(array $doc): array
    {
        $context = $doc['@context'] ?? [];
        if (is_string($context)) $context = [];
        if (isset($doc['@graph']) && is_array($doc['@graph'])) {
            return [$context, $doc['@graph']];
        }
        // Single-node document — wrap in array.
        return [$context, [$doc]];
    }

    private static function nodeToTurtle(array $node, array $prefixes, int &$bnCounter, array &$queue): string
    {
        $subject = $node['@id'] ?? ('_:b' . (++$bnCounter));
        $lines = self::turtlePairs($node, $prefixes, $bnCounter, $queue, '    ');
        if (empty($lines)) return '';
        return self::iriOrBlank($subject, $prefixes) . "\n" . implode(" ;\n", $lines) . " .\n";
    }

    /** The predicate-object lines of one node, its nested blank nodes written inline. */
    private static function turtlePairs(array $node, array $prefixes, int &$bnCounter, array &$queue, string $indent): array
    {
        $lines = [];
        if (isset($node['@type'])) {
            $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
            $lines[] = $indent . 'a ' . implode(', ', array_map(fn($t) => self::iriOrBlank($t, $prefixes), $types));
        }
        foreach ($node as $key => $value) {
            if (str_starts_with($key, '@')) continue;
            $predicate = self::iriOrBlank($key, $prefixes);
            foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $v) {
                $lines[] = $indent . $predicate . ' ' . self::valueToTurtle($v, $prefixes, $bnCounter, $queue, $indent);
            }
        }
        return $lines;
    }

    private static function valueToTurtle($v, array $prefixes, int &$bnCounter, array &$queue, string $indent = '    '): string
    {
        if (is_array($v)) {
            if (isset($v['@id'])) {
                // An embedded node with properties of its own is written as its own node.
                if (count(array_diff(array_keys($v), ['@id'])) > 0) $queue[] = $v;
                return self::iriOrBlank($v['@id'], $prefixes);
            }
            if (array_key_exists('@value', $v)) {
                if (!isset($v['@language']) && !isset($v['@type']) && !is_string($v['@value'])) {
                    return self::valueToTurtle($v['@value'], $prefixes, $bnCounter, $queue, $indent);
                }
                $lit = '"' . self::escapeTurtleLit((string) $v['@value']) . '"';
                if (isset($v['@language'])) $lit .= '@' . preg_replace('/[^a-zA-Z0-9-]/', '', $v['@language']);
                if (isset($v['@type']))     $lit .= '^^' . self::iriOrBlank($v['@type'], $prefixes);
                return $lit;
            }
            // A blank node: its properties inline, as a Turtle property list.
            $inner = self::turtlePairs($v, $prefixes, $bnCounter, $queue, $indent . '    ');
            return $inner ? "[\n" . implode(" ;\n", $inner) . "\n{$indent}]" : '[]';
        }
        if (is_bool($v))  return $v ? 'true' : 'false';
        if (self::isJsonInteger($v)) return self::integerLexical($v);
        if (is_float($v)) return '"' . self::canonicalDouble($v) . '"^^xsd:double';
        return '"' . self::escapeTurtleLit((string) $v) . '"';
    }

    /** JSON-LD writes a number with a fraction (or of 1e21 and over) as xsd:double, any other as xsd:integer. */
    private static function canonicalDouble(float $v): string
    {
        return sprintf('%.15E', $v);
    }

    private static function isJsonInteger($v): bool
    {
        return is_int($v) || (is_float($v) && is_finite($v) && floor($v) === $v && abs($v) < 1e21);
    }

    private static function integerLexical($v): string
    {
        return is_int($v) ? (string) $v : sprintf('%.0F', $v);
    }

    /** The datatype JSON-LD gives a native value, or null for a string. */
    private static function nativeDatatype($v): ?string
    {
        if (is_bool($v))  return 'http://www.w3.org/2001/XMLSchema#boolean';
        if (self::isJsonInteger($v)) return 'http://www.w3.org/2001/XMLSchema#integer';
        if (is_float($v)) return 'http://www.w3.org/2001/XMLSchema#double';
        return null;
    }

    private static function iriOrBlank(string $v, array $prefixes): string
    {
        if (str_starts_with($v, '_:')) return $v;
        // Is this already a prefixed name (ns:local)?
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9]*):[a-zA-Z][A-Za-z0-9._-]*$/', $v, $m) && isset($prefixes[$m[1]])) {
            return $v;
        }
        // Full IRI — try to compact against a known prefix.
        foreach ($prefixes as $p => $iri) {
            if (str_starts_with($v, $iri) && substr($v, strlen($iri)) !== '') {
                $local = substr($v, strlen($iri));
                if (preg_match('/^[a-zA-Z][A-Za-z0-9._-]*$/', $local)) {
                    return $p . ':' . $local;
                }
            }
        }
        return '<' . $v . '>';
    }

    private static function escapeTurtleLit(string $s): string
    {
        return str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\"', '\n', '\r', '\t'], $s);
    }

    // ---- RDF/XML -----------------------------------------------------

    private static function nodeToRdfXml(array $node, array $nsMap, string $indent = '  '): string
    {
        $about = $node['@id'] ?? null;
        $types = isset($node['@type']) ? (is_array($node['@type']) ? $node['@type'] : [$node['@type']]) : [];
        $typeTag = 'rdf:Description';
        $extraTypes = $types;
        if ($types && ($compact = self::xmlCompactName($types[0], $nsMap))) {
            $typeTag = $compact;
            $extraTypes = array_slice($types, 1);
        }
        $attr = '';
        if ($about !== null) {
            $attr = str_starts_with($about, '_:')
                ? ' rdf:nodeID="' . htmlspecialchars(substr($about, 2), ENT_XML1 | ENT_QUOTES) . '"'
                : ' rdf:about="' . htmlspecialchars($about, ENT_XML1 | ENT_QUOTES) . '"';
        }
        $in = $indent . '  ';
        $xml = "{$indent}<{$typeTag}{$attr}>\n";
        foreach ($extraTypes as $t) {
            $xml .= "{$in}<rdf:type rdf:resource=\"" . htmlspecialchars(self::expand($t, $nsMap), ENT_XML1 | ENT_QUOTES) . "\"/>\n";
        }

        foreach ($node as $k => $v) {
            if (str_starts_with($k, '@')) continue;
            $tag = self::xmlCompactName($k, $nsMap) ?: str_replace([':'], ['_'], $k);
            foreach (is_array($v) && array_is_list($v) ? $v : [$v] as $val) {
                if (is_array($val) && array_key_exists('@value', $val)) {
                    $lang = isset($val['@language']) ? ' xml:lang="' . htmlspecialchars($val['@language'], ENT_XML1 | ENT_QUOTES) . '"' : '';
                    $type = $val['@type'] ?? (isset($val['@language']) ? null : self::nativeDatatype($val['@value']));
                    $dt   = $type ? ' rdf:datatype="' . htmlspecialchars(self::expand($type, $nsMap), ENT_XML1 | ENT_QUOTES) . '"' : '';
                    $xml .= "{$in}<{$tag}{$lang}{$dt}>" . htmlspecialchars(self::xmlLexical($val['@value']), ENT_XML1) . "</{$tag}>\n";
                } elseif (is_array($val) && isset($val['@id']) && count(array_diff(array_keys($val), ['@id'])) === 0) {
                    $ref = str_starts_with($val['@id'], '_:')
                        ? ' rdf:nodeID="' . htmlspecialchars(substr($val['@id'], 2), ENT_XML1 | ENT_QUOTES) . '"'
                        : ' rdf:resource="' . htmlspecialchars($val['@id'], ENT_XML1 | ENT_QUOTES) . '"';
                    $xml .= "{$in}<{$tag}{$ref}/>\n";
                } elseif (is_array($val)) {
                    // A nested node, blank or embedded with an @id: written in full inside the property.
                    $xml .= "{$in}<{$tag}>\n" . self::nodeToRdfXml($val, $nsMap, $in . '  ') . "{$in}</{$tag}>\n";
                } else {
                    $dt = ($d = self::nativeDatatype($val)) ? ' rdf:datatype="' . $d . '"' : '';
                    $xml .= "{$in}<{$tag}{$dt}>" . htmlspecialchars(self::xmlLexical($val), ENT_XML1) . "</{$tag}>\n";
                }
            }
        }
        $xml .= "{$indent}</{$typeTag}>\n";
        return $xml;
    }

    private static function xmlLexical($v): string
    {
        if (is_bool($v))  return $v ? 'true' : 'false';
        if (self::isJsonInteger($v)) return self::integerLexical($v);
        if (is_float($v)) return self::canonicalDouble($v);
        return (string) $v;
    }

    private static function xmlCompactName(string $v, array $nsMap): ?string
    {
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9]*):([a-zA-Z][A-Za-z0-9._-]*)$/', $v, $m)) {
            if (isset($nsMap[$m[1]])) return $m[1] . ':' . $m[2];
        }
        foreach ($nsMap as $p => $iri) {
            if (str_starts_with($v, $iri)) {
                $local = substr($v, strlen($iri));
                if (preg_match('/^[a-zA-Z][A-Za-z0-9._-]*$/', $local)) return $p . ':' . $local;
            }
        }
        return null;
    }

    private static function expand(string $v, array $nsMap): string
    {
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9]*):(.+)$/', $v, $m) && isset($nsMap[$m[1]])) {
            return $nsMap[$m[1]] . $m[2];
        }
        return $v;
    }
}
