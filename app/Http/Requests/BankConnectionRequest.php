<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'provider' => [
                'required',
                'string',
                Rule::in(['bridge', 'budget_insight', 'nordigen', 'plaid']),
            ],
            'return_url' => ['nullable', 'url', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'institution_id' => ['nullable', 'string', 'max:100'],
            'country_code' => [
                'nullable',
                'string',
                'size:2',
                Rule::in(['FR', 'ES', 'IT', 'DE', 'GB', 'US', 'CA']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'provider.required' => 'Le provider bancaire est obligatoire',
            'provider.in' => 'Provider non supporté. Utilisez: bridge, budget_insight, nordigen, plaid',
            'return_url.url' => 'L\'URL de retour doit être valide',
            'webhook_url.url' => 'L\'URL webhook doit être valide',
            'country_code.size' => 'Code pays doit faire 2 caractères (ex: FR)',
            'country_code.in' => 'Code pays non supporté',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normaliser les données
        if ($this->has('country_code')) {
            $this->merge([
                'country_code' => strtoupper($this->country_code),
            ]);
        }

        if ($this->has('provider')) {
            $this->merge([
                'provider' => strtolower($this->provider),
            ]);
        }
    }

    /**
     * Get validated data with defaults
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'return_url' => config('app.frontend_url').'/banking/callback',
            'webhook_url' => config('banking.bridge.webhook_url'),
            'country_code' => 'FR',
        ], $validated);
    }
}
