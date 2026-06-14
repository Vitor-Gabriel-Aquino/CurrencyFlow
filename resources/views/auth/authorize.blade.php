<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize - CurrencyFlow</title>
    <link rel="stylesheet" href="{{ asset('css/auth/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth/authorize.css') }}">
</head>
<body>
    <main>
        <section class="auth-panel" aria-labelledby="authorize-title">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">CF</div>
                <p class="brand-name">CurrencyFlow</p>
            </div>

            <h1 id="authorize-title" class="heading">Authorize application</h1>

            <p class="summary">
                <strong>{{ $client->name }}</strong> is requesting permission to access your CurrencyFlow account.
            </p>

            @if (count($scopes) > 0)
                <section class="permissions" aria-labelledby="permissions-title">
                    <h2 id="permissions-title">Requested permissions</h2>
                    <ul>
                        @foreach ($scopes as $scope)
                            <li>{{ $scope->description }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="actions">
                <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button class="button-secondary" type="submit">Cancel</button>
                </form>

                <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                    @csrf
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button class="button-primary" type="submit">Authorize</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
