<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type'           => ['required', Rule::in(['mensuel', 'annuel'])],
            'month'          => ['nullable', 'integer', 'min:1', 'max:12', 'required_if:type,mensuel'],
            'year'           => ['required', 'integer', 'min:2000', 'max:2100'],
            'planned_amount' => ['required', 'numeric', 'min:0'],
            'label'          => ['nullable', 'string', 'max:150'],
            'currency_code'  => ['nullable', 'string', 'max:10', Rule::exists('currencies', 'code')],
            'category_id'    => ['nullable', Rule::exists('categories', 'id')->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('user_id')->orWhere('user_id', $this->user()->id);
                });
            })],
        ];
    }
}
