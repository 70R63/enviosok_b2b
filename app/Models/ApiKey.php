<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = [
        'api_client_id',
        'name',
        'key_hash',
        'key_prefix',
        'environment',
        'active',
        'last_used_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function usageLogs()
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    public function billingRequests()
    {
        return $this->hasMany(
            ApiBillingRequest::class,
            'api_key_id'
        );
    }
}