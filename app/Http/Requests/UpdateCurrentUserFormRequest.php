<?php

namespace App\Http\Requests;

use App\Domain\Shared\ValueObjects\CountryCode;
use App\Domain\Shared\ValueObjects\CurrencyCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrentUserFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('country_code')) {
            $normalized['country_code'] = (string) CountryCode::fromString($this->input('country_code'));
        }

        if ($this->has('preferred_currency_code')) {
            $normalized['preferred_currency_code'] = (string) CurrencyCode::fromString($this->input('preferred_currency_code'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'country_code' => ['sometimes', 'required', 'string', 'size:2', 'exists:countries,code'],
            'preferred_currency_code' => ['sometimes', 'required', 'string', 'size:3', 'exists:currencies,code'],
            'current_password' => ['required_with:password', 'string', 'current_password:api'],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
