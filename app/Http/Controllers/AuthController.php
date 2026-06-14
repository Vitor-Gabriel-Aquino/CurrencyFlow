<?php

namespace App\Http\Controllers;

use App\Application\Auth\RegisterUser;
use App\Application\Auth\RevokeCurrentAccessToken;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function register(Request $request, RegisterUser $registerUser): JsonResponse
    {
        $request->merge([
            'country_code' => strtoupper((string) $request->input('country_code')),
            'preferred_currency_code' => strtoupper((string) $request->input('preferred_currency_code')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'country_code' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'preferred_currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
        ]);

        $user = $registerUser->handle([
            'role_id' => Role::query()->where('name', Role::EMPLOYEE)->firstOrFail()->id,
            'country_id' => Country::query()->where('code', $validated['country_code'])->firstOrFail()->id,
            'preferred_currency_id' => Currency::query()->where('code', $validated['preferred_currency_code'])->firstOrFail()->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        return response()->json([
            'data' => $this->userData($user),
        ], 201);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function currentUser(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->userData($user),
        ]);
    }

    public function revokeToken(Request $request, RevokeCurrentAccessToken $revokeCurrentAccessToken): JsonResponse
    {
        $revokeCurrentAccessToken->handle($request->user());

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userData($user): array
    {
        $user->loadMissing(['role', 'country', 'preferredCurrency']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->name,
            'country' => [
                'code' => $user->country->code,
                'name' => $user->country->name,
            ],
            'preferred_currency' => [
                'code' => $user->preferredCurrency->code,
                'name' => $user->preferredCurrency->name,
                'exponent' => $user->preferredCurrency->exponent,
            ],
        ];
    }
}
