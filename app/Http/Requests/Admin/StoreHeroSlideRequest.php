<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreHeroSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image'                         => ['nullable', 'file', 'mimes:jpeg,png,webp', 'max:5120'],
            'sort_order'                    => ['nullable', 'integer'],
            'is_active'                     => ['nullable', 'boolean'],
            'translations'                  => ['required', 'array'],
            'translations.*.title'          => ['required', 'string', 'max:300'],
            'translations.*.subtitle'       => ['required', 'string', 'max:500'],
            'translations.*.cta_primary'    => ['nullable', 'string', 'max:100'],
            'translations.*.cta_secondary'  => ['nullable', 'string', 'max:100'],
        ];
    }
}
