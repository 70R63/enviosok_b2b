<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiWebhookDeliveryAttempt extends Model
{
    protected $fillable = [
        'api_webhook_delivery_id',
        'attempt_number',
        'started_at',
        'finished_at',
        'duration_ms',
        'request_url',
        'request_headers_json',
        'response_status',
        'response_headers_json',
        'response_body',
        'error_message',
        'successful',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
        'request_headers_json' => 'array',
        'response_status' => 'integer',
        'response_headers_json' => 'array',
        'successful' => 'boolean',
    ];

    public function delivery()
    {
        return $this->belongsTo(
            ApiWebhookDelivery::class,
            'api_webhook_delivery_id'
        );
    }
}
