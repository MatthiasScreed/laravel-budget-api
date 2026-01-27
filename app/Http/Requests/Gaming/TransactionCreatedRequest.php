<?php

namespace App\Http\Requests\Gaming;

use Illuminate\Foundation\Http\FormRequest;

class TransactionCreatedRequest extends FormRequest
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
        return [
            'transaction_id' => [
                'required',
                'integer',
                'exists:transactions,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'transaction_id.required' => 'L\'ID de la transaction est obligatoire.',
            'transaction_id.integer' => 'L\'ID de la transaction doit être un nombre entier.',
            'transaction_id.exists' => 'Cette transaction n\'existe pas.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->transaction_id) {
                // Vérifier que la transaction appartient à l'utilisateur connecté
                $transaction = \App\Models\Transaction::find($this->transaction_id);
                if ($transaction && $transaction->user_id !== auth()->id()) {
                    $validator->errors()->add('transaction_id', 'Cette transaction ne vous appartient pas.');
                }
            }
        });
    }
}
