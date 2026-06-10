<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2cIdentityVerification extends Model
{
    protected $table = 'b2c_identity_verifications';

    protected $fillable = [
        'user_id',
        'ine_front',
        'ine_back',
        'selfie_with_ine',
        'status',
        'comments',
        'reviewed_at',
        'reviewed_by',
    ];
}
