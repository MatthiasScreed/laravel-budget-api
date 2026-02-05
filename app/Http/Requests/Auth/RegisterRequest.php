<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ✅ Règles password simplifiées pour éviter les erreurs
        $passwordRules = ['required', 'string', 'confirmed', 'min:8'];

        // En production, ajouter les règles strictes
        if (config('app.env') === 'production') {
            $passwordRules[] = Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        }

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => $passwordRules,
            'password_confirmation' => ['required', 'string'],
            'terms_accepted' => [
                'required',
                'accepted', // Accepte: true, 1, "1", "yes", "on"
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
            'terms_accepted.required' => 'Vous devez accepter les conditions d\'utilisation.',
            'terms_accepted.accepted' => 'Vous devez accepter les conditions d\'utilisation.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Debug logging
        Log::info('📝 RegisterRequest - Données reçues:', [
            'keys' => array_keys($this->all()),
            'has_password_confirmation' => $this->has('password_confirmation'),
            'has_terms' => $this->has('terms_accepted'),
            'terms_value' => $this->input('terms_accepted'),
        ]);

        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->input('email')),
            ]);
        }

        // ✅ Convertir terms_accepted en boolean si c'est une string
        if ($this->has('terms_accepted')) {
            $terms = $this->input('terms_accepted');
            $this->merge([
                'terms_accepted' => filter_var($terms, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $terms,
            ]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('❌ RegisterRequest - Validation échouée:', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->except(['password', 'password_confirmation']),
        ]);

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
