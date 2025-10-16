<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'sku',
        'description',
        'price',
        'original_price',
        'sale_price',
        'image',
        'gallery',
        'category',
        'brand',
        'tags',
        'attributes',
        'short_description',
        'how_to_use',
        'ingredients',
        'weight',
        'featured',
        'status', // 'active', 'inactive'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'featured' => 'boolean',
        'attributes' => 'array',
        'tags' => 'array',
        'gallery' => 'array',
    ];

    /**
     * Get the stock for the product.
     */
    public function stock()
    {
        return $this->hasOne(Stock::class);
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}