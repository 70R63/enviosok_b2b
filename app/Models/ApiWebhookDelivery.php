<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiWebhookDelivery extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_DELIVERED = 'DELIVERED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_RETRY = 'RETRY';

    protected $fillable = [
        'api_webhook_endpoint_id',
        'api_client_id',
        'api_billing_request_id',
        'event_id',
        'event',
        'status',
        'attempts',
        'max_attempts',
        'signature_timestamp',
        'signature',
        'payload_json',
        'next_attempt_at',
        'lock_token',
        'locked_at',
        'last_attempt_at',
        'delivered_at',
        'response_status',
        'response_time_ms',
        'response_body',
        'error_message',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'signature_timestamp' => 'integer',
        'next_attempt_at' => 'datetime',
        'locked_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'response_status' => 'integer',
        'response_time_ms' => 'integer',
    ];

    public function endpoint()
    {
        return $this->belongsTo(
            ApiWebhookEndpoint::class,
            'api_webhook_endpoint_id'
        );
    }

    public function client()
    {
        return $this->belongsTo(
            ApiClient::class,
            'api_client_id'
        );
    }

    public function billingRequest()
    {
        return $this->belongsTo(
            ApiBillingRequest::class,
            'api_billing_request_id'
        );
    }


    public function attemptHistory()
    {
        return $this->hasMany(
            ApiWebhookDeliveryAttempt::class,
            'api_webhook_delivery_id'
        )->orderByDesc('attempt_number');
    }

    public function payload(): array
    {
        $decoded = json_decode(
            $this->payload_json,
            true
        );

        return is_array($decoded) ? $decoded : [];
    }
}
