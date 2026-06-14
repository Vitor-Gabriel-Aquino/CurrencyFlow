<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - CurrencyFlow</title>
    <link rel="stylesheet" href="{{ asset('css/auth/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
</head>
<body>
    <main>
        <section class="auth-panel" aria-labelledby="login-title">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">CF</div>
                <p class="brand-name">CurrencyFlow</p>
            </div>

            <h1 id="login-title" class="heading">Sign in</h1>
            <p class="subheading">Access your workspace to continue managing payment requests.</p>

            @if ($errors->any())
                <div class="alert" role="alert">
                    Unable to sign in with the provided credentials.
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <div class="form-row">
                    <label class="checkbox">
                        <input name="remember" type="checkbox" value="1">
                        Remember me
                    </label>
                </div>

                <button class="button-primary button-full" type="submit">Sign in</button>
            </form>
        </section>
    </main>
</body>
</html>
