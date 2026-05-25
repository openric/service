<?php

/**
 * ExplorerPageTest - ensures the explorer page loads and contains the root container
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 */

namespace Tests\Feature;

use Tests\TestCase;

class ExplorerPageTest extends TestCase
{
    public function test_explorer_page_loads(): void
    {
        $response = $this->get('/admin/ric/explorer');
        $response->assertStatus(200);
        $response->assertSee('ric-explorer-root');
    }
}
