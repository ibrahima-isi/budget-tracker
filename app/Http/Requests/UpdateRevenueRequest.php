<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRevenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'source'       => ['required', 'string', 'max:150'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'revenue_date' => ['required', 'date'],
            'note'         => ['nullable', 'string'],
            'currency_code' => ['nullable', 'string', 'max:10', Rule::exists('currencies', 'code')],
        ];
    }
}
