<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2cIdentityVerification extends Model
{
    use HasFactory;

    public const STATUS_UNVERIFIED = 'SIN_VERIFICAR';
    public const STATUS_PENDING = 'PENDIENTE';
    public const STATUS_CORRECTION = 'CORRECCION_REQUERIDA';
    public const STATUS_APPROVED = 'APROBADA';
    public const STATUS_REJECTED = 'RECHAZADA';

    protected $table = 'b2c_identity_verifications';

    protected $fillable = [
        'user_id',
        'ine_front',
        'ine_back',
        'selfie_with_ine',
        'document_disk',
        'status',
        'submitted_at',
        'comments',
        'correction_documents',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'correction_documents' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function events(): HasMany
    {
        return $this->hasMany(
            B2cIdentityVerificationEvent::class,
            'identity_verification_id'
        );
    }

    public function isPendingReview(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_PENDING,
                'EN_REVISION',
            ],
            true
        );
    }

    public function hasCompleteDocuments(): bool
    {
        return filled($this->ine_front)
            && filled($this->ine_back)
            && filled($this->selfie_with_ine);
    }
}