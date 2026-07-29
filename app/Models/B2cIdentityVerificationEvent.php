<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2cIdentityVerificationEvent extends Model
{
    protected $fillable = [
        'identity_verification_id',
        'user_id',
        'event_type',
        'from_status',
        'to_status',
        'comments',
        'metadata',
        'performed_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(
            B2cIdentityVerification::class,
            'identity_verification_id'
        );
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'performed_by'
        );
    }
}