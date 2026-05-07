<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignEuDeclarationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month_year_received'        => ['required', 'string', 'regex:/^\d{2}\/\d{4}$/'],
            'member_state_of_entry'      => ['required', 'string', 'max:100'],
            'place_of_entry'             => ['required', 'string', 'max:200'],
            'self_transported'           => ['required', 'boolean'],
            'month_year_transport_ended' => [
                Rule::requiredIf($this->boolean('self_transported')),
                'nullable',
                'string',
                'regex:/^\d{2}\/\d{4}$/',
            ],
            'representative_name'        => ['required', 'string', 'max:200'],
            'representative_title'       => ['nullable', 'string', 'max:100'],
            'signed_name'                => ['required', 'string', 'max:200', 'regex:/^[A-Z][A-Z .\-]*$/'],
            'accepted_terms'             => ['required', 'accepted'],
            'signature_data'             => ['required', 'string', 'regex:/^data:image\/png;base64,/'],
        ];
    }

    public function messages(): array
    {
        return [
            'month_year_received.regex'             => 'month_year_received must be in MM/YYYY format.',
            'month_year_transport_ended.regex'       => 'month_year_transport_ended must be in MM/YYYY format.',
            'month_year_transport_ended.required'    => 'month_year_transport_ended is required when self_transported is true.',
            'signed_name.regex'                     => 'signed_name must be uppercase letters, spaces, dots, or hyphens only.',
            'accepted_terms.accepted'               => 'You must accept the declaration terms.',
            'signature_data.regex'                  => 'signature_data must be a valid base64-encoded PNG (data:image/png;base64,...).',
        ];
    }
}
