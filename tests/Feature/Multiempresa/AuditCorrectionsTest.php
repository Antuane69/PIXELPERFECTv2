<?php

namespace Tests\Feature\Multiempresa;

use Tests\TestCase;

class AuditCorrectionsTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
