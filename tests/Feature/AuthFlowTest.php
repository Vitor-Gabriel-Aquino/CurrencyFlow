<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Employee',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'test@example.com');

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

    public function test_current_user_route_requires_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_current_user_route_returns_controlled_response_shape(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => 'employee@example.com',
                ],
            ]);
    }

    public function test_current_access_token_can_be_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $token = $this->issueAccessTokenFor($user);

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
                'scope' => 'payments:read payments:create',
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
                'state' => 'phpunit-test',
            ]));

        $authorizeResponse
            ->assertOk()
            ->assertSee('CurrencyFlow Frontend')
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
