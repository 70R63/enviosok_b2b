<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiBillingRequestItem extends Model
{
    protected $fillable = [
        'api_billing_request_id',
        'line_number',
        'client_item_id',
        'category',
        'product_service_code',
        'unit_code',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'subtotal',
        'tax_object',
        'tax_rate',
        'tax_amount',
        'total',
        'metadata',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:6',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:6',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function billingRequest()
    {
        return $this->belongsTo(
            ApiBillingRequest::class,
            'api_billing_request_id'
        );
    }
}
