<?php

namespace App\Domain\Network\Commerce\Models;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class TenantSaasInvoiceRequest extends Model
{
    public const STATUSES = ['REQUESTED', 'PROCESSING', 'ISSUED', 'REJECTED'];

    protected $fillable = ['tenant_id', 'tenant_saas_order_id', 'platform_payment_attempt_id', 'requested_by_user_id', 'issued_by_user_id', 'status', 'subtotal', 'tax_amount', 'total', 'currency', 'fiscal_snapshot', 'pdf_path', 'xml_path', 'requested_at', 'issued_at', 'rejected_at', 'rejection_reason'];
    protected $casts = ['subtotal'=>'decimal:2', 'tax_amount'=>'decimal:2', 'total'=>'decimal:2', 'fiscal_snapshot'=>'array', 'requested_at'=>'datetime', 'issued_at'=>'datetime', 'rejected_at'=>'datetime'];
    protected static function booted(): void { self::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function order() { return $this->belongsTo(TenantSaasOrder::class, 'tenant_saas_order_id'); }
    public function paymentAttempt() { return $this->belongsTo(PlatformPaymentAttempt::class, 'platform_payment_attempt_id'); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function issuedBy() { return $this->belongsTo(User::class, 'issued_by_user_id'); }
}
