<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
