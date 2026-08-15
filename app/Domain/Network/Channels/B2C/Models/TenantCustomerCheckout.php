<?php

namespace App\Domain\Network\Channels\B2C\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class TenantCustomerCheckout extends Model
{
    public const STATUSES = ['DRAFT', 'PENDING_PAYMENT', 'PAID', 'EXPIRED', 'CANCELED'];
    public const PAYMENT_STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELED', 'REFUNDED'];
    protected $fillable = ['tenant_id', 'customer_profile_id', 'tenant_operation_id', 'status', 'payment_status', 'currency', 'shipping_amount', 'evidence_amount', 'subtotal_amount', 'tax_rate', 'tax_amount', 'total_amount', 'quote_snapshot', 'shipping_data_snapshot', 'proof_option_snapshot', 'payment_provider', 'payment_reference', 'expires_at', 'paid_at'];
    protected $casts = ['quote_snapshot' => 'array', 'shipping_data_snapshot' => 'array', 'proof_option_snapshot' => 'array', 'shipping_amount' => 'decimal:2', 'evidence_amount' => 'decimal:2', 'subtotal_amount' => 'decimal:2', 'tax_rate' => 'decimal:4', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'expires_at' => 'datetime', 'paid_at' => 'datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $checkout) => $checkout->uuid ??= (string) Str::uuid());
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customerProfile(): BelongsTo { return $this->belongsTo(TenantCustomerProfile::class, 'customer_profile_id'); }
    public function operation(): BelongsTo { return $this->belongsTo(TenantOperation::class, 'tenant_operation_id'); }
}
