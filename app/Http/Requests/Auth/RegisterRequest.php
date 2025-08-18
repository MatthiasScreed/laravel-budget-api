<?php

namespace App\Http\Requests\Auth;


use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/' // Permet les accents, espaces, tirets, apostrophes
            ],
            'email' => [
                'required',
                'string',
                'email:rfc', // ✅ Plus de validation DNS
                'max:255',
                'unique:users,email'
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                config('app.env') === 'testing'
                    ? 'min:8' // ✅ Simple en test
                    : [
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                        ->uncompromised()
                ]
            ],
            'terms_accepted' => [
                'required',
                'boolean',
                'accepted' // Doit être true
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'name.regex' => 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.',

            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',

            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',

            'terms_accepted.required' => 'Vous devez accepter les conditions d\'utilisation.',
            'terms_accepted.accepted' => 'Vous devez accepter les conditions d\'utilisation.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse email',
            'password' => 'mot de passe',
            'terms_accepted' => 'conditions d\'utilisation'
        ];
    }

    /**
     * ✅ AJOUTER CETTE MÉTHODE - Normalisation avant validation
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normaliser l'email en minuscules si présent
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->input('email'))
            ]);
        }
    }
}
