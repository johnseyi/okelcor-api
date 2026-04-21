<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery'                  => ['required', 'array'],
            'delivery.name'             => ['required', 'string', 'max:200'],
            'delivery.email'            => ['required', 'email', 'max:255'],
            'delivery.phone'            => ['required', 'string', 'max:50'],
            'delivery.address'          => ['required', 'string', 'max:300'],
            'delivery.city'             => ['required', 'string', 'max:100'],
            'delivery.postal_code'      => ['required', 'string', 'max:20'],
            'delivery.country'          => ['required', 'string', 'max:100'],
            'payment_method'            => ['required', 'string', 'in:creditcard,ideal,paypal,klarna,bancontact'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.sku'               => ['required', 'string', 'max:50'],
            'items.*.brand'             => ['required', 'string', 'max:100'],
            'items.*.name'              => ['required', 'string', 'max:200'],
            'items.*.size'              => ['required', 'string', 'max:50'],
            'items.*.unit_price'        => ['required', 'numeric', 'min:0'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],
            'items.*.product_id'        => ['nullable', 'integer'],
            'vat_number'                => ['nullable', 'string', 'min:4', 'max:20'],
            'fet_addon'                 => ['nullable', 'array'],
            'fet_addon.product_name'    => ['required_with:fet_addon', 'string', 'max:200'],
            'fet_addon.sku'             => ['required_with:fet_addon', 'string', 'max:50'],
            'fet_addon.unit_price'      => ['required_with:fet_addon', 'numeric', 'min:0'],
            'fet_addon.quantity'        => ['required_with:fet_addon', 'integer', 'min:1'],
        ];
    }
}
