<?php

namespace App\Domain\Shipping\LastMile\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class TenantDeliveryProofOption extends Model
{
    public const RECEIVER_POLICIES = ['RECIPIENT_ONLY', 'AUTHORIZED_PERSON', 'ANY_PERSON_AT_ADDRESS', 'RECEPTION_OR_SECURITY'];
    public const EVIDENCE_FIELDS = ['require_receiver_name', 'require_signature', 'require_photo', 'require_gps'];

    protected $fillable = ['tenant_id', 'code', 'name', 'description', 'require_receiver_name', 'require_receiver_type', 'require_signature', 'require_photo', 'require_gps', 'receiver_policy', 'max_delivery_attempts', 'surcharge_amount', 'currency', 'is_default', 'is_active', 'sort_order'];
    protected $casts = ['require_receiver_name' => 'boolean', 'require_receiver_type' => 'boolean', 'require_signature' => 'boolean', 'require_photo' => 'boolean', 'require_gps' => 'boolean', 'is_default' => 'boolean', 'is_active' => 'boolean', 'surcharge_amount' => 'decimal:2'];

    protected static function booted(): void
    {
        self::creating(fn (self $option) => $option->uuid ??= (string) Str::uuid());
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
