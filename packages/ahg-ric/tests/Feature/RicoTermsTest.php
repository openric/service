<?php

/**
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of OpenRiC.
 *
 * OpenRiC is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgRic\Tests\Feature;

use AhgRic\Support\RicoTerms;
use Tests\TestCase;

/** Pre-1.1 relation predicates from the shared taxonomy leave the service as RiC-O 1.1 names. */
class RicoTermsTest extends TestCase
{
    public function test_legacy_predicates_map_to_rico_1_1(): void
    {
        $this->assertSame('rico:hasOrHadInstantiation', RicoTerms::canonical('rico:hasInstantiation'));
        $this->assertSame('rico:hasOrHadHolder', RicoTerms::canonical('rico:heldBy'));
        $this->assertSame('rico:isOrWasPerformedBy', RicoTerms::canonical('rico:isOrWasPerformedBy'));
        $this->assertSame('openricx:isAssociatedWith', RicoTerms::canonical('openricx:isAssociatedWith'));
        $this->assertNull(RicoTerms::canonical(null));
    }

    public function test_no_target_is_itself_a_legacy_name(): void
    {
        $this->assertSame([], array_values(array_intersect(RicoTerms::LEGACY, array_keys(RicoTerms::LEGACY))));
    }

    public function test_rows_are_mapped_in_place(): void
    {
        $row = (object) ['rico_predicate' => 'rico:isInstantiationOf', 'inverse_predicate' => 'rico:hasInstantiation'];
        RicoTerms::row($row);
        $this->assertSame('rico:isOrWasInstantiationOf', $row->rico_predicate);
        $this->assertSame('rico:hasOrHadInstantiation', $row->inverse_predicate);
    }
}
