<?php

namespace App\Domain\Network\Security\Models;

use Illuminate\Database\Eloquent\Model;

final class NetworkTwoFactorEvent extends Model
{
    public const UPDATED_AT = null;
    protected $table = 'network_two_factor_events';
    protected $guarded = [];
    protected $casts = ['metadata' => 'array'];
}
