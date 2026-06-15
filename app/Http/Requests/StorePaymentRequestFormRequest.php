<?php

namespace App\Http\Requests;

use App\Domain\Shared\ValueObjects\CurrencyCode;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_code' => (string) CurrencyCode::fromString($this->input('currency_code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
        ];
    }
}
