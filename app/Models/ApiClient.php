<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    protected $fillable = [
        'crm_client_id',
        'user_id',
        'name',
        'company_name',
        'email',
        'plan',
        'monthly_limit',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function crmClient()
    {
        return $this->belongsTo(CrmClient::class, 'crm_client_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function keys()
    {
        return $this->hasMany(ApiKey::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    public function clientProducts()
    {
        return $this->hasMany(ApiClientProduct::class);
    }

    public function billingRequests()
    {
        return $this->hasMany(
            ApiBillingRequest::class,
            'api_client_id'
        );
    }

    public function products()
    {
        return $this->belongsToMany(
            ApiProduct::class,
            'api_client_products'
        )
            ->withPivot([
                'active',
                'monthly_limit',
            ])
            ->withTimestamps();
    }
}