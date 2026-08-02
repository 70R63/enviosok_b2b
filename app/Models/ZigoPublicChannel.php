<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZigoPublicChannel extends Model
{
    protected $fillable = [
        'channel',
        'label',
        'value',
        'url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
