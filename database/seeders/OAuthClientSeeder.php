<?php

namespace Database\Seeders;

use App\Models\OAuthClientCorsOrigin;
use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

class OAuthClientSeeder extends Seeder
{
    public const FRONTEND_CLIENT_ID = '019ec29e-86dc-70bd-9de9-157bc6e2f735';

    public function run(): void
    {
        $client = Client::query()->updateOrCreate(
            ['id' => self::FRONTEND_CLIENT_ID],
            [
                'owner_id' => null,
                'owner_type' => null,
                'name' => 'CurrencyFlow Frontend',
                'secret' => null,
                'provider' => 'users',
                'redirect_uris' => ['http://localhost:3000/auth/callback'],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'revoked' => false,
            ],
        );

        OAuthClientCorsOrigin::query()->updateOrCreate(
            [
                'oauth_client_id' => $client->id,
                'origin' => 'http://localhost:3000',
            ],
            [
                'origin' => 'http://localhost:3000',
            ],
        );
    }
}
