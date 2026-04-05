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
            'file'       => ['required', 'file', 'mimes:jpeg,png,jpg,gif,webp,svg,mp4,mov,avi,webm', 'max:51200'],
            'collection' => ['nullable', 'string', Rule::in(['products', 'articles', 'hero', 'brands', 'categories', 'general'])],
            'alt_text'   => ['nullable', 'string', 'max:300'],
        ];
    }
}
