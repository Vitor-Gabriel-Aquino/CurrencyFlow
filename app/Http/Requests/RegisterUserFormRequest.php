<?php

namespace App\Http\Requests;

use App\Domain\Shared\ValueObjects\CountryCode;
use App\Domain\Shared\ValueObjects\CurrencyCode;
use Illuminate\Foundation\Http\FormRequest;

class RegisterUserFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => (string) CountryCode::fromString($this->input('country_code')),
            'preferred_currency_code' => (string) CurrencyCode::fromString($this->input('preferred_currency_code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'country_code' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'preferred_currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
        ];
    }
}
