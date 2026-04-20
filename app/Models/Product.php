<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'sku',
        'brand',
        'name',
        'size',
        'spec',
        'season',
        'type',
        'price',
        'description',
        'primary_image',
        'is_active',
        'sort_order',
        'width',
        'height',
        'rim',
        'load_index',
        'speed_rating',
        'stock',
        'cost_price',
        'ebay_listed',
        'ebay_listing_id',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'cost_price'   => 'decimal:2',
        'is_active'    => 'boolean',
        'stock'        => 'integer',
        'ebay_listed'  => 'boolean',
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }
}
