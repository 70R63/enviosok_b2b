<?php

namespace App\Domain\Network\Onboarding\Models;

use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class SaasOnboardingApplication extends Model
{
    public const DRAFT = 'DRAFT';
    public const PENDING_PAYMENT = 'PENDING_PAYMENT';
    public const PAID = 'PAID';
    public const PROVISIONING = 'PROVISIONING';
    public const ACTIVE = 'ACTIVE';
    public const FAILED = 'FAILED';
    public const CANCELLED = 'CANCELLED';
    public const EXPIRED = 'EXPIRED';

    public const STATUSES = [
        self::DRAFT, self::PENDING_PAYMENT, self::PAID, self::PROVISIONING,
        self::ACTIVE, self::FAILED, self::CANCELLED, self::EXPIRED,
    ];

    public const BILLING_PERIODS = ['monthly', 'annual'];

    protected $table = 'saas_onboarding_applications';

    protected $fillable = [
        'public_token', 'status', 'contact_name', 'contact_last_name',
        'contact_email', 'contact_phone', 'company_name', 'company_legal_name',
        'tax_id', 'selected_plan_id', 'billing_period', 'selected_modules_json',
        'requested_operations', 'commercial_snapshot_json', 'subtotal',
        'tax_amount', 'total', 'currency', 'requested_subdomain',
        'reserved_subdomain_key', 'subdomain_reserved_until', 'purchase_key',
        'paid_at', 'provisioning_started_at', 'activated_at', 'failed_at',
        'cancelled_at', 'expired_at', 'tenant_id', 'owner_user_id',
        'failure_code', 'failure_context_json', 'lock_version',
    ];

    protected $casts = [
        'selected_modules_json' => 'array',
        'commercial_snapshot_json' => 'array',
        'failure_context_json' => 'array',
        'requested_operations' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'subdomain_reserved_until' => 'datetime',
        'paid_at' => 'datetime',
        'provisioning_started_at' => 'datetime',
        'activated_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $application): void {
            $application->uuid ??= (string) Str::uuid();
            $application->public_token ??= Str::random(64);
        });
    }

    public function setContactEmailAttribute(string $value): void
    {
        $this->attributes['contact_email'] = Str::lower(trim($value));
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'selected_plan_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SaasOnboardingEvent::class, 'onboarding_application_id');
    }
}
