<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image'                    => ['nullable', 'file', 'mimes:jpeg,png,webp', 'max:5120'],
            'sort_order'               => ['nullable', 'integer'],
            'is_active'                => ['nullable', 'boolean'],
            'translations'             => ['sometimes', 'array'],
            'translations.*.title'     => ['required_with:translations', 'string', 'max:200'],
            'translations.*.label'     => ['required_with:translations', 'string', 'max:100'],
            'translations.*.subtitle'  => ['required_with:translations', 'string', 'max:500'],
        ];
    }
}
