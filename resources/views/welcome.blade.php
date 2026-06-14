<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CurrencyFlow</title>
    <link rel="stylesheet" href="{{ asset('css/auth/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth/home.css') }}">
</head>
<body>
    <main>
        <section class="auth-panel" aria-labelledby="page-title">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">CF</div>
                <p class="brand-name">CurrencyFlow</p>
            </div>

            <h1 id="page-title">Authentication workspace</h1>
            <p>This web area supports the OAuth authorization flow for CurrencyFlow API clients.</p>

            @auth
                <div class="session">
                    <strong>Signed in as</strong>
                    <p>{{ auth()->user()->email }}</p>
                </div>

                <div class="actions">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="button-secondary" type="submit">Sign out</button>
                    </form>
                </div>
            @else
                <div class="actions">
                    <a class="button-primary" href="{{ route('login') }}">Sign in</a>
                </div>
            @endauth
        </section>
    </main>
</body>
</html>
