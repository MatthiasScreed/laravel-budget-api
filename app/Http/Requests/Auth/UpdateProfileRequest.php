<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/',
            ],
            'email' => [
                'sometimes',
                'string',
                'email:rfc', // ✅ Plus de validation DNS
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'nullable',
                'string',
                'regex:/^(\+33|0)[1-9](\d{8})$/', // Format téléphone français
            ],
            'date_of_birth' => [
                'nullable',
                'date',
                'before:today',
                'after:1900-01-01',
            ],
            'currency' => [
                'sometimes',
                'string',
                'in:EUR,USD,GBP,CHF', // Devises supportées
            ],
            'timezone' => [
                'sometimes',
                'string',
                'timezone',
            ],
            'language' => [
                'sometimes',
                'string',
                'in:fr,en,es,de', // Langues supportées
            ],
            'preferences' => [
                'sometimes',
                'array',
            ],
            'preferences.notifications' => [
                'sometimes',
                'array',
            ],
            'preferences.theme' => [
                'sometimes',
                'string',
                'in:light,dark,auto',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'name.regex' => 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'phone.regex' => 'Le numéro de téléphone doit être au format français valide.',
            'date_of_birth.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'date_of_birth.after' => 'La date de naissance doit être postérieure à 1900.',
            'currency.in' => 'La devise doit être EUR, USD, GBP ou CHF.',
            'timezone.timezone' => 'Le fuseau horaire doit être valide.',
            'language.in' => 'La langue doit être fr, en, es ou de.',
            'preferences.theme.in' => 'Le thème doit être light, dark ou auto.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }
    }
}
