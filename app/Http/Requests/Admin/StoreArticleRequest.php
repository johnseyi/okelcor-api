<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug'                          => ['required', 'string', 'max:300', Rule::unique('articles', 'slug')],
            'image'                         => ['nullable', 'string', 'max:500'],
            'published_at'                  => ['nullable', 'date'],
            'is_published'                  => ['nullable', 'boolean'],
            'sort_order'                    => ['nullable', 'integer'],
            'translations'                  => ['required', 'array'],
            'translations.*.category'       => ['required', 'string', 'max:100'],
            'translations.*.title'          => ['required', 'string', 'max:500'],
            'translations.*.read_time'      => ['nullable', 'string', 'max:30'],
            'translations.*.summary'        => ['required', 'string'],
            'translations.*.body'           => ['required', 'array'],
            'translations.*.body.*'         => ['string'],
        ];
    }
}
