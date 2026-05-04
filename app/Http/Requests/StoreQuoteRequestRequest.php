<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'         => ['required', 'string', 'max:200'],
            'company_name'      => ['nullable', 'string', 'max:200'],
            'email'             => ['required', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:50'],
            'country'           => ['required', 'string', 'max:100'],
            'business_type'     => ['nullable', 'string', 'max:100'],
            'tyre_category'     => ['required', 'string', 'max:100'],
            'brand_preference'  => ['nullable', 'string', 'max:200'],
            'tyre_size'         => ['nullable', 'string', 'max:100'],
            'quantity'          => ['required', 'string', 'max:100'],
            'budget_range'      => ['nullable', 'string', 'max:100'],
            'delivery_location' => ['required', 'string', 'max:300'],
            'delivery_timeline' => ['nullable', 'string', 'max:100'],
            'notes'             => ['required', 'string'],
            'delivery_address'      => ['nullable', 'string', 'max:300'],
            'delivery_city'         => ['nullable', 'string', 'max:100'],
            'delivery_postal_code'  => ['nullable', 'string', 'max:30'],
            'vat_number'            => ['nullable', 'string', 'min:4', 'max:20'],
            'attachment'            => ['nullable', 'file', 'mimes:pdf,csv,xls,xlsx', 'max:10240'],
        ];
    }
}
