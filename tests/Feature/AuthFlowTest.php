<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $response = $this->postJson('/api/register', [
            'name' => 'Test Employee',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'country_code' => 'PT',
            'preferred_currency_code' => 'EUR',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'test@example.com')
            ->assertJsonPath('data.role', 'employee')
            ->assertJsonPath('data.country.code', 'PT')
            ->assertJsonPath('data.preferred_currency.code', 'EUR');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_login_form_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_user_can_login_with_session(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $this->withSession(['_token' => 'test-token'])->post('/login', [
            '_token' => 'test-token',
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_logout_from_web_session(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Signed in as')
            ->assertSee('Sign out');

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->post('/logout', [
                '_token' => 'test-token',
            ])
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_finance_user_can_manage_oauth_clients(): void
    {
        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $finance = User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();

        $this->actingAs($finance)
            ->get('/developer/oauth-clients')
            ->assertOk()
            ->assertSee('OAuth clients')
            ->assertSee('Signed in as')
            ->assertSee('marta.kowalska@example.com');
    }

    public function test_employee_cannot_manage_oauth_clients(): void
    {
        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $employee = User::query()->where('email', 'ana.silva@example.com')->firstOrFail();

        $this->actingAs($employee)
            ->get('/developer/oauth-clients')
            ->assertForbidden();
    }

    public function test_finance_user_can_create_public_oauth_client(): void
    {
        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $finance = User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();

        $this->actingAs($finance)
            ->withSession(['_token' => 'test-token'])
            ->post('/developer/oauth-clients', [
                '_token' => 'test-token',
                'name' => 'Partner Portal',
                'redirect_uri' => 'https://partner.example.com/auth/callback',
                'allowed_cors_origin' => 'https://partner.example.com',
            ])
            ->assertRedirect('/developer/oauth-clients')
            ->assertSessionHas('created_client_id');

        $this->assertDatabaseHas('oauth_clients', [
            'name' => 'Partner Portal',
            'secret' => null,
            'provider' => 'users',
            'revoked' => false,
        ]);
        $this->assertDatabaseHas('oauth_client_cors_origins', [
            'origin' => 'https://partner.example.com',
        ]);
    }

    public function test_oauth_client_creation_rejects_cors_origin_with_path(): void
    {
        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $finance = User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();

        $this->actingAs($finance)
            ->withSession(['_token' => 'test-token'])
            ->post('/developer/oauth-clients', [
                '_token' => 'test-token',
                'name' => 'Partner Portal',
                'redirect_uri' => 'https://partner.example.com/auth/callback',
                'allowed_cors_origin' => 'https://partner.example.com/app',
            ])
            ->assertSessionHasErrors('allowed_cors_origin');
    }

    public function test_oauth_client_creation_rejects_plain_http_cors_origin_outside_localhost(): void
    {
        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $finance = User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();

        $this->actingAs($finance)
            ->withSession(['_token' => 'test-token'])
            ->post('/developer/oauth-clients', [
                '_token' => 'test-token',
                'name' => 'Partner Portal',
                'redirect_uri' => 'https://partner.example.com/auth/callback',
                'allowed_cors_origin' => 'http://partner.example.com',
            ])
            ->assertSessionHasErrors('allowed_cors_origin');
    }

    public function test_finance_user_can_revoke_oauth_client(): void
    {
        $this->seed([
            ReferenceDataSeeder::class,
            UserSeeder::class,
        ]);

        $finance = User::query()->where('email', 'marta.kowalska@example.com')->firstOrFail();
        $client = Client::factory()
            ->asPublic()
            ->create([
                'name' => 'Partner Portal',
                'provider' => 'users',
                'redirect_uris' => ['https://partner.example.com/auth/callback'],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'revoked' => false,
            ]);
        $token = Token::query()->create([
            'id' => 'test-access-token',
            'user_id' => $finance->id,
            'client_id' => $client->id,
            'name' => 'Partner Portal Token',
            'scopes' => ['payments:read'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);
        $refreshToken = RefreshToken::query()->create([
            'id' => 'test-refresh-token',
            'access_token_id' => $token->id,
            'revoked' => false,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($finance)
            ->withSession(['_token' => 'test-token'])
            ->delete("/developer/oauth-clients/{$client->id}", [
                '_token' => 'test-token',
            ])
            ->assertRedirect('/developer/oauth-clients');

        $this->assertDatabaseHas('oauth_clients', [
            'id' => $client->id,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $token->id,
            'revoked' => true,
        ]);
        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'id' => $refreshToken->id,
            'revoked' => true,
        ]);
    }

    public function test_current_user_route_requires_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_current_user_update_route_requires_authentication(): void
    {
        $this->patchJson('/api/user', [
            'name' => 'Updated Name',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_current_user_route_returns_controlled_response_shape(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user, 'profile:read');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => 'employee@example.com',
                    'role' => 'employee',
                    'country' => [
                        'code' => 'PT',
                        'name' => 'Portugal',
                    ],
                    'preferred_currency' => [
                        'code' => 'EUR',
                        'name' => 'Euro',
                        'exponent' => 2,
                    ],
                ],
            ]);
    }

    public function test_current_user_route_requires_profile_read_scope(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user, 'payments:read');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertForbidden();
    }

    public function test_current_user_can_update_profile(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user, 'profile:update');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/user', [
                'name' => 'Updated Employee',
                'email' => 'updated@example.com',
                'country_code' => 'br',
                'preferred_currency_code' => 'brl',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Updated Employee')
            ->assertJsonPath('data.email', 'updated@example.com')
            ->assertJsonPath('data.role', 'employee')
            ->assertJsonPath('data.country.code', 'BR')
            ->assertJsonPath('data.preferred_currency.code', 'BRL');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Employee',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_current_user_update_requires_profile_update_scope(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user, 'profile:read');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/user', [
                'name' => 'Updated Employee',
            ])
            ->assertForbidden();
    }

    public function test_current_user_update_rejects_duplicate_email(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
        ]);
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user, 'profile:update');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/user', [
                'email' => $existingUser->email,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_current_user_can_update_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user, 'profile:update');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/user', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'employee@example.com');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_current_user_password_update_requires_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user, 'profile:update');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/user', [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
    }

    public function test_current_user_password_update_rejects_invalid_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user, 'profile:update');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/user', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
    }

    public function test_current_access_token_can_be_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user, 'profile:read');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/tokens/current')
            ->assertOk()
            ->assertJsonPath('message', 'Token revoked successfully.');

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_authorization_code_with_pkce_can_issue_token_for_api_access(): void
    {
        $user = User::factory()->create([
            'email' => 'oauth-user@example.com',
            'password' => 'password',
        ]);

        $client = Client::factory()
            ->asPublic()
            ->create([
                'name' => 'CurrencyFlow Frontend',
                'redirect_uris' => ['http://localhost:3000/auth/callback'],
                'grant_types' => ['authorization_code', 'refresh_token'],
            ]);

        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $authorizeResponse = $this
            ->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->id,
                'redirect_uri' => 'http://localhost:3000/auth/callback',
                'response_type' => 'code',
                'scope' => 'profile:read payments:read payments:create',
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
                'state' => 'phpunit-test',
            ]));

        $authorizeResponse
            ->assertOk()
            ->assertSee('CurrencyFlow Frontend')
            ->assertSee('Read the authenticated user profile')
            ->assertSee('Read payment requests')
            ->assertSee('Create payment requests');

        $this->withSession(['_token' => 'test-token']);

        $approvalResponse = $this->post('/oauth/authorize', [
            '_token' => 'test-token',
            'auth_token' => session('authToken'),
        ]);

        $approvalResponse->assertRedirect();

        parse_str(parse_url($approvalResponse->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('phpunit-test', $query['state']);
        $this->assertNotEmpty($query['code']);

        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'code' => $query['code'],
            'code_verifier' => $codeVerifier,
        ]);

        $accessToken = $tokenResponse
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'oauth-user@example.com');
    }

    private function issueAccessTokenFor(User $user, string $scope = 'payments:read'): string
    {
        $client = Client::factory()
            ->asPublic()
            ->create([
                'name' => 'CurrencyFlow Frontend',
                'redirect_uris' => ['http://localhost:3000/auth/callback'],
                'grant_types' => ['authorization_code', 'refresh_token'],
            ]);

        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $this
            ->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->id,
                'redirect_uri' => 'http://localhost:3000/auth/callback',
                'response_type' => 'code',
                'scope' => $scope,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
                'state' => 'token-helper',
            ]))
            ->assertOk();

        $this->withSession(['_token' => 'test-token']);

        $approvalResponse = $this->post('/oauth/authorize', [
            '_token' => 'test-token',
            'auth_token' => session('authToken'),
        ]);

        $approvalResponse->assertRedirect();

        parse_str(parse_url($approvalResponse->headers->get('Location'), PHP_URL_QUERY), $query);

        $accessToken = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'code' => $query['code'],
            'code_verifier' => $codeVerifier,
        ])
            ->assertOk()
            ->json('access_token');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $accessToken;
    }
}
