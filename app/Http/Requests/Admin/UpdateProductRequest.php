<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'sku'         => ['sometimes', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($productId)],
            'brand'       => ['sometimes', 'string', 'max:100'],
            'name'        => ['sometimes', 'string', 'max:200'],
            'size'        => ['sometimes', 'string', 'max:50'],
            'spec'        => ['nullable', 'string', 'max:50'],
            'season'      => ['sometimes', Rule::in(['Summer', 'Winter', 'All Season', 'All-Terrain'])],
            'type'        => ['sometimes', Rule::in(['PCR', 'TBR', 'Used', 'OTR'])],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'price_b2b'   => ['nullable', 'numeric', 'min:0'],
            'price_b2c'   => ['nullable', 'numeric', 'min:0'],
            'description' => ['sometimes', 'string'],
            'primary_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:5120'],
            'is_active'     => ['nullable', 'boolean'],
            'in_stock'      => ['nullable', 'boolean'],
            'sort_order'    => ['nullable', 'integer'],
            'ebay_listed'   => ['nullable', 'boolean'],
            'ebay_item_id'  => ['nullable', 'string', 'max:100'],
        ];
    }
}
