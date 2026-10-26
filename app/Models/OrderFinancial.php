<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFinancial extends Model
{
    use HasFactory;

    protected $table = 'order_financials';

    protected $fillable = [
        'order_id',
        'selling_price',
        'cost_price',
        'gateway_fee',
        'net_profit',
        'currency',
    ];

    protected $casts = [
        'selling_price' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'gateway_fee' => 'decimal:4',
        'net_profit' => 'decimal:4',
    ];

    /**
     * Relationship: Order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
