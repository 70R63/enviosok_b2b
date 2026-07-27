<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ApiWebhookEndpoint extends Model
{
    protected $fillable = [
        'api_client_id',
        'name',
        'environment',
        'url',
        'secret_encrypted',
        'secret_prefix',
        'events',
        'active',
        'last_delivery_at',
    ];

    protected $hidden = [
        'secret_encrypted',
    ];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'last_delivery_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(
            ApiClient::class,
            'api_client_id'
        );
    }

    public function deliveries()
    {
        return $this->hasMany(
            ApiWebhookDelivery::class,
            'api_webhook_endpoint_id'
        );
    }

    public function supportsEvent(string $event): bool
    {
        return in_array(
            $event,
            $this->events ?? [],
            true
        );
    }

    public function decryptSecret(): string
    {
        return Crypt::decryptString(
            $this->secret_encrypted
        );
    }
}
