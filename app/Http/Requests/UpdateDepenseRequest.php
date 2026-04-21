<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'budget_id'    => ['required', Rule::exists('budgets', 'id')->where('user_id', $this->user()->id)],
            'categorie_id' => ['nullable', 'exists:categories,id'],
            'libelle'      => ['required', 'string', 'max:200'],
            'montant'      => ['required', 'numeric', 'min:0'],
            'date_depense' => ['required', 'date'],
            'note'         => ['nullable', 'string', 'max:1000'],
        ];
    }
}
