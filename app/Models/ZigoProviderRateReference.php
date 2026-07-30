<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZigoProviderRateReference extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $fillable = [
        'agreement_service_id',
        'name',
        'version',
        'status',
        'currency',
        'tax_percentage',
        'included_weight_kg',
        'base_price',
        'additional_weight_unit_kg',
        'additional_weight_price',
        'valid_from',
        'valid_to',
        'source_reference',
        'document_path',
        'document_original_name',
        'document_mime_type',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'tax_percentage' => 'decimal:2',
        'included_weight_kg' => 'decimal:3',
        'base_price' => 'decimal:2',
        'additional_weight_unit_kg' => 'decimal:3',
        'additional_weight_price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function agreementService(): BelongsTo
    {
        return $this->belongsTo(
            ZigoAgreementService::class,
            'agreement_service_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}
