<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
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
            'type'          => ['required', Rule::in(['mensuel', 'annuel'])],
            'mois'          => ['nullable', 'integer', 'min:1', 'max:12', 'required_if:type,mensuel'],
            'annee'         => ['required', 'integer', 'min:2000', 'max:2100'],
            'montant_prevu' => ['required', 'numeric', 'min:0'],
            'libelle'       => ['nullable', 'string', 'max:150'],
        ];
    }
}
