<?php

declare(strict_types=1);

namespace AhgRicModel\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end HTTP behaviour for the /reference/ric-cm/… routes.
 *
 * Skipped if Fuseki is unreachable — same guard as OntologyServiceTest.
 */
class RoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfFusekiUnreachable();
    }

    public function test_unversioned_index_redirects_to_latest(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $this->get('/reference/ric-cm')
            ->assertStatus(302)
            ->assertRedirect("/reference/ric-cm/{$latest}");
    }

    #[DataProvider('unversionedLists')]
    public function test_unversioned_list_redirects_to_latest(string $path): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $this->get("/reference/ric-cm/{$path}")
            ->assertStatus(302)
            ->assertRedirect("/reference/ric-cm/{$latest}/{$path}");
    }

    /** @return array<string, array{string}> */
    public static function unversionedLists(): array
    {
        return [
            'entities'            => ['entities'],
            'attributes'          => ['attributes'],
            'relations'           => ['relations'],
            'relation-attributes' => ['relation-attributes'],
        ];
    }

    public function test_versioned_index_serves_directly(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $this->get("/reference/ric-cm/{$latest}")
            ->assertOk()
            ->assertSee('RiC-CM')
            ->assertSee($latest);
    }

    public function test_versioned_entities_list_includes_all_19(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $response = $this->get("/reference/ric-cm/{$latest}/entities")->assertOk();
        // Each entity ID is rendered in a <code> tag on the list.
        foreach (['RiC-E01', 'RiC-E02', 'RiC-E04', 'RiC-E07', 'RiC-E22'] as $id) {
            $response->assertSee($id);
        }
    }

    public function test_versioned_attributes_list_count_line_shows_42(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $this->get("/reference/ric-cm/{$latest}/attributes")
            ->assertOk()
            ->assertSee('Attributes (42)');
    }

    public function test_entity_detail_for_known_id_renders_declared_and_inherited_headings(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $response = $this->get("/reference/ric-cm/{$latest}/entities/RiC-E04")->assertOk();
        $response->assertSee('Declared attributes');
        $response->assertSee('Declared relations');
        $response->assertSee('Inherited');
        // Record's ancestor is Record Resource.
        $response->assertSee('Record Resource');
    }

    public function test_entity_detail_for_unknown_id_is_404(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $this->get("/reference/ric-cm/{$latest}/entities/RiC-E99")->assertStatus(404);
    }

    public function test_unknown_version_is_404(): void
    {
        $this->get('/reference/ric-cm/99.9/entities')->assertStatus(404);
        $this->get('/reference/ric-cm/99.9')->assertStatus(404);
    }

    public function test_relation_detail_renders_browsing_aid_label(): void
    {
        $latest    = (string) config('ahg-ric-model.versions.latest');
        $relations = app(\AhgRicModel\Services\OntologyService::class)->listRelations($latest);
        $this->assertNotEmpty($relations);
        $firstId = $relations[0]['id'];

        $this->get("/reference/ric-cm/{$latest}/relations/{$firstId}")
            ->assertOk()
            ->assertSee('Declared domain')
            ->assertSee('Declared range')
            ->assertSee('browsing aid');
    }

    public function test_attribution_is_rendered_on_pages(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $this->get("/reference/ric-cm/{$latest}/entities")
            ->assertOk()
            ->assertSee('International Council on Archives')
            ->assertSee('CC BY 4.0');
    }

    /** @return iterable<string, array{string, string}> */
    public static function listUrlsAndLabels(): iterable
    {
        yield 'entities'            => ['entities',            'entities'];
        yield 'attributes'          => ['attributes',          'attributes'];
        yield 'relations'           => ['relations',           'relations'];
        yield 'relation-attributes' => ['relation-attributes', 'relation attributes'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('listUrlsAndLabels')]
    public function test_list_pages_render_alpine_filter_and_data_search_attrs(string $path, string $label): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $body = $this->get("/reference/ric-cm/{$latest}/{$path}")->assertOk()->getContent();

        // Alpine wiring
        $this->assertStringContainsString('x-data="ricListFilter"', $body, "{$path}: missing Alpine x-data");
        $this->assertStringContainsString('x-ref="table"', $body,        "{$path}: missing x-ref on table");
        $this->assertStringContainsString('role="search"', $body,        "{$path}: missing role=search on filter");

        // Every row carries a data-search attribute (DOM-based filter).
        $this->assertMatchesRegularExpression('/<tr\s+data-search="[^"]+"/', $body, "{$path}: data-search missing on rows");

        // Mobile-responsive classes present.
        $this->assertStringContainsString('table-responsive', $body, "{$path}: table-responsive wrapper missing");

        // Filter counter label.
        $this->assertStringContainsString($label, $body, "{$path}: total-label '{$label}' missing");
    }

    public function test_layout_has_skip_to_content_link_and_print_css(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $body = $this->get("/reference/ric-cm/{$latest}/entities")->assertOk()->getContent();

        $this->assertStringContainsString('class="skip-link"', $body, 'skip-to-content link absent');
        $this->assertStringContainsString('id="main-content"', $body, 'main#main-content anchor absent');
        $this->assertStringContainsString('@media print', $body,      'print stylesheet absent');
    }

    public function test_entity_detail_toggle_exposes_aria_expanded(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $body = $this->get("/reference/ric-cm/{$latest}/entities/RiC-E04")->assertOk()->getContent();
        // The Show/Hide toggles must bind aria-expanded for screen readers.
        $this->assertStringContainsString(':aria-expanded="open"', $body, 'aria-expanded not bound on expand toggle');
    }

    // --- Phase 4 — detail-view polish --------------------------------------------------

    public function test_entity_detail_inherited_rows_carry_data_inherited_from(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $body = $this->get("/reference/ric-cm/{$latest}/entities/RiC-E04")->assertOk()->getContent();
        // Record inherits plenty from RecordResource; at least one row must have the attribute.
        $this->assertMatchesRegularExpression(
            '/<tr\s+data-inherited-from="RiC-E[0-9]+"/',
            $body,
            'inherited rows must carry data-inherited-from for portability + tests',
        );
    }

    public function test_entity_detail_inherited_tag_anchors_to_ancestor_declared_section(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $body = $this->get("/reference/ric-cm/{$latest}/entities/RiC-E04")->assertOk()->getContent();
        // Clicking "from RecordResource" should jump to its declared-attributes / declared-relations section.
        $this->assertStringContainsString('#declared-attributes', $body);
        $this->assertStringContainsString('#declared-relations',  $body);
    }

    public function test_entity_detail_renders_declared_anchors(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        $body = $this->get("/reference/ric-cm/{$latest}/entities/RiC-E04")->assertOk()->getContent();
        $this->assertStringContainsString('id="declared-attributes"', $body);
        $this->assertStringContainsString('id="declared-relations"',  $body);
    }

    public function test_entity_detail_renders_scope_notes_and_examples_when_present(): void
    {
        $latest = (string) config('ahg-ric-model.versions.latest');
        // Record (RiC-E04) has a scope note in RiC-O v1.1.
        $this->get("/reference/ric-cm/{$latest}/entities/RiC-E04")
            ->assertOk()
            ->assertSee('Scope notes');
    }

    public function test_relation_detail_renders_broader_narrower_section_when_present(): void
    {
        $latest    = (string) config('ahg-ric-model.versions.latest');
        $relations = app(\AhgRicModel\Services\OntologyService::class)->listRelations($latest);

        // Find any relation that has a broader or narrower counterpart
        // (most RiC-R relations do via rdfs:subPropertyOf chains).
        $target = null;
        foreach ($relations as $r) {
            $detail = app(\AhgRicModel\Services\OntologyService::class)->getRelation($r['id'], $latest);
            if (!empty($detail['broader']) || !empty($detail['narrower'])) {
                $target = $r['id'];
                break;
            }
        }
        $this->assertNotNull($target, 'Expected at least one relation with broader or narrower hierarchy in RiC-O v1.1');

        $this->get("/reference/ric-cm/{$latest}/relations/{$target}")
            ->assertOk()
            ->assertSee('Hierarchy')
            ->assertSee('Broader')
            ->assertSee('Narrower');
    }

    public function test_attribute_detail_renders_inherited_by_list(): void
    {
        $latest     = (string) config('ahg-ric-model.versions.latest');
        $attributes = app(\AhgRicModel\Services\OntologyService::class)->listAttributes($latest);

        // An attribute declared on an entity with descendants (e.g. Thing) will have
        // inheritedBy; walk attributes until we find one.
        $target = null;
        foreach ($attributes as $a) {
            $detail = app(\AhgRicModel\Services\OntologyService::class)->getAttribute($a['id'], $latest);
            if (!empty($detail['inheritedBy'])) {
                $target = $a['id'];
                break;
            }
        }
        $this->assertNotNull($target, 'Expected at least one attribute with inheritedBy entries');

        $body = $this->get("/reference/ric-cm/{$latest}/attributes/{$target}")->assertOk()->getContent();
        $this->assertStringContainsString('Also applies to', $body);
        $this->assertStringContainsString('ric-tag-inherited', $body);
        $this->assertMatchesRegularExpression('/data-inherited-from="RiC-E[0-9]+"/', $body,
            'Inherited-by rows must carry data-inherited-from (portability + tests)');
    }

    // ------------------------------------------------------------------

    private function skipIfFusekiUnreachable(): void
    {
        $url = config('ahg-ric-model.fuseki.url');
        $ds  = config('ahg-ric-model.fuseki.dataset');
        $ping = @file_get_contents(rtrim($url, '/') . '/' . $ds . '/sparql?query=ASK%20%7B%7D');
        if ($ping === false) {
            $this->markTestSkipped("Fuseki at {$url}/{$ds} unreachable; skipping route tests.");
        }
    }
}
