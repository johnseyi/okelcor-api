<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:200'],
            'email'   => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'inquiry' => ['required', 'string'],
        ];
    }
}
