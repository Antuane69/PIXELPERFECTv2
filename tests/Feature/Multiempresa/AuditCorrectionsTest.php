<?php

namespace Tests\Feature\Multiempresa;

use Tests\TestCase;

class AuditCorrectionsTest extends TestCase
{
    public function test_home_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
