<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiClientProduct extends Model
{
    protected $fillable = [
        'api_client_id',
        'api_product_id',
        'active',
        'monthly_limit',
    ];

    protected $casts = [
        'active' => 'boolean',
        'monthly_limit' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(
            ApiClient::class,
            'api_client_id'
        );
    }

    public function product()
    {
        return $this->belongsTo(
            ApiProduct::class,
            'api_product_id'
        );
    }
}
