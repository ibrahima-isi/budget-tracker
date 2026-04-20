<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepenseRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'budget_id'    => ['required', 'exists:budgets,id'],
            'categorie_id' => ['required', 'exists:categories,id'],
            'libelle'      => ['required', 'string', 'max:200'],
            'montant'      => ['required', 'numeric', 'min:0'],
            'date_depense' => ['required', 'date'],
            'note'         => ['nullable', 'string'],
        ];
    }
}
