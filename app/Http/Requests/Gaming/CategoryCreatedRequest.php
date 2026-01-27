<?php

namespace App\Http\Requests\Gaming;

use Illuminate\Foundation\Http\FormRequest;

class CategoryCreatedRequest extends FormRequest
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
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'L\'ID de la catégorie est obligatoire.',
            'category_id.integer' => 'L\'ID de la catégorie doit être un nombre entier.',
            'category_id.exists' => 'Cette catégorie n\'existe pas.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->category_id) {
                $category = \App\Models\Category::find($this->category_id);

                // Vérifier que la catégorie appartient à l'utilisateur
                if ($category && $category->user_id !== auth()->id()) {
                    $validator->errors()->add('category_id', 'Cette catégorie ne vous appartient pas.');
                }
            }
        });
    }
}
