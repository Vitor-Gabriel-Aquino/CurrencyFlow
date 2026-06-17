<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OAuth Clients - CurrencyFlow</title>
    <link rel="stylesheet" href="{{ asset('css/auth/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/developer/oauth-clients.css') }}">
</head>
<body>
    <main class="portal-shell">
        <section class="portal-panel" aria-labelledby="page-title">
            <header class="portal-header">
                <div>
                    <div class="brand">
                        <div class="brand-mark" aria-hidden="true">CF</div>
                        <p class="brand-name">CurrencyFlow</p>
                    </div>
                    <h1 id="page-title">OAuth clients</h1>
                    <p>Create public OAuth clients for browser-based applications using Authorization Code with PKCE.</p>
                </div>

                <a class="button-secondary" href="{{ url('/') }}">Back</a>
            </header>

            <div class="session-bar">
                <div>
                    <span>Signed in as</span>
                    <strong>{{ auth()->user()->email }}</strong>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button-secondary" type="submit">Sign out</button>
                </form>
            </div>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            @if (session('created_client_id'))
                <div class="client-id">
                    <span>Client ID</span>
                    <code>{{ session('created_client_id') }}</code>
                </div>
            @endif

            <form class="client-form" method="POST" action="{{ route('developer.oauth-clients.store') }}">
                @csrf

                <div class="field">
                    <label for="name">Client name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="120" required>
                    @error('name')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="redirect_uri">Redirect URI</label>
                    <input id="redirect_uri" name="redirect_uri" type="url" value="{{ old('redirect_uri', 'http://localhost:3000/auth/callback') }}" maxlength="2048" required>
                    @error('redirect_uri')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <button class="button-primary" type="submit">Create public client</button>
            </form>

            <div class="client-list">
                @forelse ($clients as $client)
                    <article class="client-row">
                        <div>
                            <h2>{{ $client->name }}</h2>
                            <p>{{ $client->id }}</p>
                            @foreach ($client->redirect_uris as $redirectUri)
                                <code>{{ $redirectUri }}</code>
                            @endforeach
                        </div>

                        <div class="client-actions">
                            @if ($client->revoked)
                                <span class="status revoked">Revoked</span>
                            @else
                                <span class="status active">Active</span>
                                <form method="POST" action="{{ route('developer.oauth-clients.destroy', $client) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button-secondary" type="submit">Revoke</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="empty">No OAuth clients created yet.</p>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
