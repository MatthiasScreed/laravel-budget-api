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
                'email:rfc,dns', // Validation email strict
                'max:255',
                'unique:users,email'
            ],
            'password' => [
                'required',
                'string',
                'confirmed', // Nécessite password_confirmation
                Password::min(8)
                    ->letters() // Au moins une lettre
                    ->mixedCase() // Majuscules et minuscules
                    ->numbers() // Au moins un chiffre
                    ->symbols() // Au moins un symbole
                    ->uncompromised() // Vérifie contre les mots de passe compromis
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
}
