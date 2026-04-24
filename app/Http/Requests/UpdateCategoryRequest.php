<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name')
                    ->where(function ($q) use ($user) {
                        $q->where(function ($inner) use ($user) {
                            $inner->whereNull('user_id')->orWhere('user_id', $user->id);
                        });
                    })
                    ->ignore($this->route('category')->id),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon'  => ['required', 'string', 'max:50'],
        ];
    }
}
