<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'  => ['required', 'email', 'max:255'],
            'locale' => ['nullable', 'string', 'in:en,de,fr,es'],
        ];
    }
}
