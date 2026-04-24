<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'budget_id'    => ['required', Rule::exists('budgets', 'id')->where('user_id', $this->user()->id)],
            'category_id'  => ['nullable', Rule::exists('categories', 'id')->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('user_id')->orWhere('user_id', $this->user()->id);
                });
            })],
            'label'        => ['required', 'string', 'max:200'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'note'         => ['nullable', 'string', 'max:1000'],
            'currency_code' => ['nullable', 'string', 'max:10', Rule::exists('currencies', 'code')],
        ];
    }
}
