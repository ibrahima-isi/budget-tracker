<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategorieRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user     = $this->user();
        $category = $this->route('category');

        return $user->is_admin || $category->user_id === $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'nom' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'nom')
                    ->where(function ($q) use ($user) {
                        $q->where(function ($inner) use ($user) {
                            $inner->whereNull('user_id')->orWhere('user_id', $user->id);
                        });
                    })
                    ->ignore($this->route('category')->id),
            ],
            'couleur' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icone'   => ['required', 'string', 'max:50'],
        ];
    }
}
