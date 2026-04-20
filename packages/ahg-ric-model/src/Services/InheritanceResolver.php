<?php

declare(strict_types=1);

namespace AhgRicModel\Services;

/**
 * Resolves declared vs. inherited attributes and relations for a RiC-CM entity.
 *
 * PORTABILITY CONTRACT — this class is deliberately kept Laravel-free. A
 * one-to-one JavaScript port should preserve method signatures and the exact
 * shape of inputs and outputs so that it can be contributed upstream to
 * DLIB-Ionian-University/ric-cm-nav (if/when they add a LICENSE). Do NOT
 * import Illuminate, Laravel facades, or Eloquent here — pure PHP only.
 *
 * INPUT SHAPES
 *
 *   $allEntities     = [
 *       ['id' => 'RiC-E01', 'name' => 'Thing',           'parents' => []],
 *       ['id' => 'RiC-E02', 'name' => 'Record Resource', 'parents' => ['RiC-E01']],
 *       ['id' => 'RiC-E04', 'name' => 'Record',          'parents' => ['RiC-E02']],
 *       ...
 *   ]
 *
 *   $allAttributes   = [
 *       ['id' => 'RiC-A01', 'name' => 'Accruals', 'domain' => ['RiC-E03'], ...],
 *       ...
 *   ]
 *
 *   $allRelations    = [
 *       ['id' => 'RiC-R016', 'name' => 'has successor',
 *        'domain' => 'RiC-E04', 'range' => 'RiC-E04', ...],
 *       ...
 *   ]
 *
 * OUTPUT SHAPE
 *
 *   [
 *       'ancestors' => [
 *           ['id' => 'RiC-E02', 'name' => 'Record Resource'],
 *           ['id' => 'RiC-E01', 'name' => 'Thing'],
 *       ],
 *       'declaredAttributes'  => [ <attribute-row>, ... ],
 *       'inheritedAttributes' => [ <attribute-row + inheritedFrom>, ... ],
 *       'declaredRelations'   => [ <relation-row>, ... ],
 *       'inheritedRelations'  => [ <relation-row + inheritedFrom>, ... ],
 *   ]
 *
 * Inherited rows carry an additional key: 'inheritedFrom' => ['id' => ..., 'name' => ...].
 * Declared rows never do. Domain and range on relations are never flattened over
 * descendants — that is a navigation concern handled separately by callers.
 */
class InheritanceResolver
{
    /**
     * Resolve declared and inherited members for one entity.
     *
     * @param string                                   $entityId      The RiC-CM ID, e.g. "RiC-E05".
     * @param array<int, array{id: string, name: string, parents: array<int, string>}>                                                       $allEntities
     * @param array<int, array{id: string, name: string, domain: array<int, string>}>                                                                       $allAttributes
     * @param array<int, array{id: string, name: string, domain: string, range: string}>                                                      $allRelations
     * @return array{
     *     ancestors: array<int, array{id: string, name: string}>,
     *     declaredAttributes: array<int, array<string, mixed>>,
     *     inheritedAttributes: array<int, array<string, mixed>>,
     *     declaredRelations: array<int, array<string, mixed>>,
     *     inheritedRelations: array<int, array<string, mixed>>
     * }
     */
    public function resolve(
        string $entityId,
        array $allEntities,
        array $allAttributes,
        array $allRelations,
    ): array {
        $entityIndex = $this->indexById($allEntities);

        if (!isset($entityIndex[$entityId])) {
            return [
                'ancestors'           => [],
                'declaredAttributes'  => [],
                'inheritedAttributes' => [],
                'declaredRelations'   => [],
                'inheritedRelations'  => [],
            ];
        }

        $ancestors = $this->ancestors($entityId, $entityIndex);

        $declaredAttributes  = [];
        $inheritedAttributes = [];
        foreach ($allAttributes as $attr) {
            $attrDomains = $attr['domain'] ?? [];
            if (in_array($entityId, $attrDomains, true)) {
                $declaredAttributes[] = $attr;
                continue;
            }
            foreach ($ancestors as $ancestor) {
                if (in_array($ancestor['id'], $attrDomains, true)) {
                    $row = $attr;
                    $row['inheritedFrom'] = ['id' => $ancestor['id'], 'name' => $ancestor['name']];
                    $inheritedAttributes[] = $row;
                    break;  // tag with nearest ancestor only
                }
            }
        }

        $declaredRelations  = [];
        $inheritedRelations = [];
        foreach ($allRelations as $rel) {
            $relDomain = $rel['domain'] ?? null;
            if ($relDomain === $entityId) {
                $declaredRelations[] = $rel;
                continue;
            }
            foreach ($ancestors as $ancestor) {
                if ($relDomain === $ancestor['id']) {
                    $row = $rel;
                    $row['inheritedFrom'] = ['id' => $ancestor['id'], 'name' => $ancestor['name']];
                    $inheritedRelations[] = $row;
                    break;
                }
            }
        }

        return [
            'ancestors'           => $ancestors,
            'declaredAttributes'  => $declaredAttributes,
            'inheritedAttributes' => $inheritedAttributes,
            'declaredRelations'   => $declaredRelations,
            'inheritedRelations'  => $inheritedRelations,
        ];
    }

    /**
     * Return an entity's ancestor chain from nearest parent to root, as
     * [{id, name}, ...]. Does NOT include the entity itself. Handles
     * multiple-inheritance by breadth-first traversal, deduplicating by id.
     *
     * @param array<int|string, array{id: string, name: string, parents: array<int, string>}> $entityIndex
     * @return array<int, array{id: string, name: string}>
     */
    public function ancestors(string $entityId, array $entityIndex): array
    {
        if (!isset($entityIndex[$entityId])) {
            return [];
        }

        $seen  = [$entityId => true];
        $queue = $entityIndex[$entityId]['parents'] ?? [];
        $chain = [];

        while (!empty($queue)) {
            $parentId = array_shift($queue);
            if (isset($seen[$parentId]) || !isset($entityIndex[$parentId])) {
                continue;
            }
            $seen[$parentId] = true;
            $parent = $entityIndex[$parentId];
            $chain[] = ['id' => $parent['id'], 'name' => $parent['name']];
            foreach (($parent['parents'] ?? []) as $grandParentId) {
                if (!isset($seen[$grandParentId])) {
                    $queue[] = $grandParentId;
                }
            }
        }

        return $chain;
    }

    /**
     * Descendants (all sub-entities) of an entity — used as a BROWSING AID
     * on relation/attribute detail pages. Never applied to domain/range
     * values themselves (no flattening). BFS, deduped.
     *
     * @param array<int, array{id: string, name: string, parents: array<int, string>}> $allEntities
     * @return array<int, array{id: string, name: string}>
     */
    public function descendants(string $entityId, array $allEntities): array
    {
        $seen        = [$entityId => true];
        $descendants = [];
        $queue       = [$entityId];

        while (!empty($queue)) {
            $currentId = array_shift($queue);
            foreach ($allEntities as $e) {
                if (in_array($currentId, $e['parents'] ?? [], true) && !isset($seen[$e['id']])) {
                    $seen[$e['id']] = true;
                    $descendants[] = ['id' => $e['id'], 'name' => $e['name']];
                    $queue[] = $e['id'];
                }
            }
        }

        return $descendants;
    }

    /**
     * @param array<int, array{id: string, name: string, parents: array<int, string>}> $entities
     * @return array<string, array{id: string, name: string, parents: array<int, string>}>
     */
    private function indexById(array $entities): array
    {
        $index = [];
        foreach ($entities as $e) {
            $index[$e['id']] = $e;
        }
        return $index;
    }
}
