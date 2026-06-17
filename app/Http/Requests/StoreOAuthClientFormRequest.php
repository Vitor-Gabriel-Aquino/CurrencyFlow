<?php

namespace App\Http\Requests;

use App\Domain\Auth\ValueObjects\CorsOrigin;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class StoreOAuthClientFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-oauth-clients') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_scalar($this->input('name')) ? trim((string) $this->input('name')) : '',
            'redirect_uri' => is_scalar($this->input('redirect_uri')) ? trim((string) $this->input('redirect_uri')) : '',
            'allowed_cors_origin' => $this->normalizedCorsOrigin(),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'redirect_uri' => ['required', 'url', 'max:2048', 'starts_with:http://localhost,https://'],
            'allowed_cors_origin' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        CorsOrigin::fromString((string) $value);
                    } catch (InvalidArgumentException $exception) {
                        $fail($exception->getMessage());
                    }
                },
            ],
        ];
    }

    private function normalizedCorsOrigin(): string
    {
        if (! is_scalar($this->input('allowed_cors_origin'))) {
            return '';
        }

        $value = trim((string) $this->input('allowed_cors_origin'));

        try {
            return CorsOrigin::fromString($value)->value();
        } catch (InvalidArgumentException) {
            return $value;
        }
    }
}
