<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCategorieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // no server-side policy — per spec "pas de policy"
    }

    public function rules(): array
    {
        return [];
    }
}
