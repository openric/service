<?php

/**
 * ExplorerPageTest - ensures the explorer page loads and contains the root container
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExplorerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_explorer_page_loads(): void
    {
        // An admin page: signed in (AdminRoutesRequireSignInTest checks that a visitor is refused).
        $response = $this->actingAs(User::factory()->create())->get('/admin/ric/explorer');
        $response->assertStatus(200);
        $response->assertSee('ric-explorer-root');
    }
}
