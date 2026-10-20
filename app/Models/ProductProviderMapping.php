<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductProviderMapping extends Model
{
    use HasFactory;

    protected $table = 'product_provider_mappings';

    protected $fillable = [
        'product_id',
        'variant_key',
        'country_name',
        'country_code',
        'provider_slug',
        'external_item_id',
        'external_category_id',
        'cost_price',
        'currency',
        'is_service',
        'required_fields',
        'is_mock',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'is_service' => 'boolean',
        'is_mock' => 'boolean',
        'is_active' => 'boolean',
        'required_fields' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
