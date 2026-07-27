<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiProduct extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
        'billable',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'billable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function clientProducts()
    {
        return $this->hasMany(ApiClientProduct::class);
    }

    public function clients()
    {
        return $this->belongsToMany(
            ApiClient::class,
            'api_client_products'
        )
            ->withPivot([
                'active',
                'monthly_limit',
            ])
            ->withTimestamps();
    }
}
