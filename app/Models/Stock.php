<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'quantity',
        'low_stock_threshold',
    ];

    /**
     * Get the product that owns the stock.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if product is in stock
     */
    public function inStock()
    {
        return $this->quantity > 0;
    }

    /**
     * Check if product is low in stock
     */
    public function lowStock()
    {
        return $this->quantity <= $this->low_stock_threshold;
    }
}