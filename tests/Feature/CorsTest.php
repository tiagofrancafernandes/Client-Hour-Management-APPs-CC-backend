<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function testVercelPreviewDomainIsAllowedInPreflightRequest(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://hourledger-99nbp9ttr-tiago-francas-projects-5cb35b7e.vercel.app',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/clients');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://hourledger-99nbp9ttr-tiago-francas-projects-5cb35b7e.vercel.app');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function testVercelProductionDomainIsAllowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://hourledger.vercel.app',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/clients');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://hourledger.vercel.app');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function testDisallowedOriginDoesNotReceiveCorsHeaders(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://malicious-untrusted-domain.com',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/clients');

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }
}
