<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_reports_a_healthy_application_without_sensitive_details(): void
    {
        Storage::fake('private-local');

        $response = $this->getJson('/up');

        $response
            ->assertOk()
            ->assertHeader('X-SIGME-Service', 'SIGME')
            ->assertExactJson(['status' => 'healthy']);
    }
}
