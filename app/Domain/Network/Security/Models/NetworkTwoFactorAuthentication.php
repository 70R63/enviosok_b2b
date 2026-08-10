<?php

namespace App\Domain\Network\Security\Models;

use Illuminate\Database\Eloquent\Model;

final class NetworkTwoFactorAuthentication extends Model
{
    protected $table = 'network_two_factor_authentications';
    protected $guarded = [];
    protected $hidden = ['secret', 'recovery_code_hashes'];
    protected $casts = [
        'secret' => 'encrypted',
        'recovery_code_hashes' => 'array',
        'enabled_at' => 'datetime',
    ];
}
