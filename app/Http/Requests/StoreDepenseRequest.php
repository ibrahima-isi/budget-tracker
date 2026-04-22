<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepenseRequest extends FormRequest
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
            'categorie_id' => ['nullable', Rule::exists('categories', 'id')->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('user_id')->orWhere('user_id', $this->user()->id);
                });
            })],
            'libelle'      => ['required', 'string', 'max:200'],
            'montant'      => ['required', 'numeric', 'min:0'],
            'date_depense' => ['required', 'date'],
            'note'         => ['nullable', 'string', 'max:1000'],
            'currency_code' => ['nullable', 'string', 'max:10', Rule::exists('currencies', 'code')],
        ];
    }
}
