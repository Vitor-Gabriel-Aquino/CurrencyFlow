<?php

namespace App\Http\Controllers;

use App\Application\Auth\RegisterUser;
use App\Application\Auth\RevokeCurrentAccessToken;
use App\Application\Auth\UpdateCurrentUser;
use App\Domain\Users\Enums\UserRole;
use App\Http\Requests\LoginFormRequest;
use App\Http\Requests\RegisterUserFormRequest;
use App\Http\Requests\UpdateCurrentUserFormRequest;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
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

    public function register(RegisterUserFormRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $validated = $request->validated();

        $user = $registerUser->handle([
            'role_id' => Role::query()->where('name', UserRole::Employee->value)->firstOrFail()->id,
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

    public function login(LoginFormRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
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

    public function updateCurrentUser(UpdateCurrentUserFormRequest $request, UpdateCurrentUser $updateCurrentUser): JsonResponse
    {
        $validated = $request->validated();
        $data = [];

        foreach (['name', 'email', 'password'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $validated[$field];
            }
        }

        if (array_key_exists('country_code', $validated)) {
            $data['country_id'] = Country::query()->where('code', $validated['country_code'])->firstOrFail()->id;
        }

        if (array_key_exists('preferred_currency_code', $validated)) {
            $data['preferred_currency_id'] = Currency::query()->where('code', $validated['preferred_currency_code'])->firstOrFail()->id;
        }

        $user = $updateCurrentUser->handle($request->user(), $data);

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
    private function userData(User $user): array
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
