<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => [
                'required',
                'string',
                'email',
                'max:255'
            ],
            'password' => [
                'required',
                'string',
                'min:1' // Au moins 1 caractère pour la connexion
            ],
            'remember' => [
                'sometimes',
                'boolean'
            ],
            'device_name' => [
                'sometimes',
                'string',
                'max:255'
            ],
            'revoke_other_tokens' => [
                'sometimes',
                'boolean'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'device_name.max' => 'Le nom de l\'appareil ne peut pas dépasser 255 caractères.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->email), // Normaliser l'email
            'remember' => $this->boolean('remember', false),
            'revoke_other_tokens' => $this->boolean('revoke_other_tokens', false)
        ]);
    }

}
