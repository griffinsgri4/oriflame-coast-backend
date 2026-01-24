<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'phone',
        'amount',
        'merchant_request_id',
        'checkout_request_id',
        'status',
        'result_code',
        'result_desc',
        'mpesa_receipt_number',
        'raw_request',
        'raw_callback',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'result_code' => 'integer',
        'raw_request' => 'array',
        'raw_callback' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

