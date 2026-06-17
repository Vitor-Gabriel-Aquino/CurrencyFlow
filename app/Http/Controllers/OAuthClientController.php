<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOAuthClientFormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

class OAuthClientController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-oauth-clients');

        $clients = Client::query()
            ->latest()
            ->get();

        return view('developer.oauth-clients.index', [
            'clients' => $clients,
        ]);
    }

    public function store(StoreOAuthClientFormRequest $request): RedirectResponse
    {
        $client = Client::query()->create([
            'owner_id' => null,
            'owner_type' => null,
            'name' => $request->validated('name'),
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => [$request->validated('redirect_uri')],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);

        return redirect()
            ->route('developer.oauth-clients.index')
            ->with('created_client_id', $client->id)
            ->with('status', 'OAuth client created successfully.');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('manage-oauth-clients');

        abort_if($client->revoked, 404);

        DB::transaction(function () use ($client): void {
            $client->forceFill([
                'revoked' => true,
            ])->save();

            Passport::token()
                ->where('client_id', $client->id)
                ->cursor()
                ->each(function (Token $token): void {
                    $token->revoke();
                    $token->refreshToken?->revoke();
                });
        });

        return redirect()
            ->route('developer.oauth-clients.index')
            ->with('status', 'OAuth client revoked successfully.');
    }
}
