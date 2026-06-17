<?php

namespace Tests\Feature;

use App\Models\OAuthClientCorsOrigin;
use Database\Seeders\OAuthClientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Tests\TestCase;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_frontend_client_allows_local_frontend_origin(): void
    {
        $this->seed(OAuthClientSeeder::class);

        $this->assertDatabaseHas('oauth_client_cors_origins', [
            'oauth_client_id' => OAuthClientSeeder::FRONTEND_CLIENT_ID,
            'origin' => 'http://localhost:3000',
        ]);

        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type, accept',
        ])
            ->options('/oauth/token')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_oauth_token_preflight_allows_frontend_origin(): void
    {
        $this->createPublicClientWithOrigin('http://localhost:3000');

        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type, accept',
        ])
            ->options('/oauth/token')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_api_preflight_allows_registered_origin(): void
    {
        $this->createPublicClientWithOrigin('https://partner.example.com');

        $this->withHeaders([
            'Origin' => 'https://partner.example.com',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization, accept',
        ])
            ->options('/api/user')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://partner.example.com');
    }

    public function test_preflight_rejects_unregistered_origin(): void
    {
        $this->createPublicClientWithOrigin('https://partner.example.com');

        $this->withHeaders([
            'Origin' => 'https://unknown.example.com',
            'Access-Control-Request-Method' => 'POST',
        ])
            ->options('/oauth/token')
            ->assertForbidden()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_preflight_rejects_origin_from_revoked_client(): void
    {
        $this->createPublicClientWithOrigin('https://partner.example.com', revoked: true);

        $this->withHeaders([
            'Origin' => 'https://partner.example.com',
            'Access-Control-Request-Method' => 'POST',
        ])
            ->options('/oauth/token')
            ->assertForbidden()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    private function createPublicClientWithOrigin(string $origin, bool $revoked = false): void
    {
        $client = Client::factory()
            ->asPublic()
            ->create([
                'name' => 'Partner Portal',
                'redirect_uris' => [$origin.'/auth/callback'],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'revoked' => $revoked,
            ]);

        OAuthClientCorsOrigin::query()->create([
            'oauth_client_id' => $client->id,
            'origin' => $origin,
        ]);
    }
}
