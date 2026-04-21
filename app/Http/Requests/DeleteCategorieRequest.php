<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCategorieRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user     = $this->user();
        $category = $this->route('category');

        return $user->is_admin || $category->user_id === $user->id;
    }

    public function rules(): array
    {
        return [];
    }
}
