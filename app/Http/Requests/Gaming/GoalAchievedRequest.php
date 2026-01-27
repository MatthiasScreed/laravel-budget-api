<?php

namespace App\Http\Requests\Gaming;

use Illuminate\Foundation\Http\FormRequest;

class GoalAchievedRequest extends FormRequest
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
            'goal_id' => [
                'required',
                'integer',
                'exists:financial_goals,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'goal_id.required' => 'L\'ID de l\'objectif est obligatoire.',
            'goal_id.integer' => 'L\'ID de l\'objectif doit être un nombre entier.',
            'goal_id.exists' => 'Cet objectif n\'existe pas.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->goal_id) {
                $goal = \App\Models\FinancialGoal::find($this->goal_id);

                // Vérifier que l'objectif appartient à l'utilisateur
                if ($goal && $goal->user_id !== auth()->id()) {
                    $validator->errors()->add('goal_id', 'Cet objectif ne vous appartient pas.');
                }

                // Vérifier que l'objectif est bien complété
                if ($goal && $goal->status !== 'completed') {
                    $validator->errors()->add('goal_id', 'Cet objectif n\'est pas encore complété.');
                }
            }
        });
    }
}
