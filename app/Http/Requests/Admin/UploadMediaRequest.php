<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'       => ['required', 'file', 'mimes:jpeg,png,webp,svg', 'max:5120'],
            'collection' => ['nullable', 'string', Rule::in(['products', 'articles', 'hero', 'brands', 'categories', 'general'])],
            'alt_text'   => ['nullable', 'string', 'max:300'],
        ];
    }
}
