<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    protected $fillable = [
        'api_client_id',
        'api_key_id',
        'api_product_id',
        'endpoint',
        'method',
        'status_code',
        'response_time_ms',
        'ip',
        'error_message',
    ];

    public function client()
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    public function product()
    {
        return $this->belongsTo(
            ApiProduct::class,
            'api_product_id'
        );
    }
}