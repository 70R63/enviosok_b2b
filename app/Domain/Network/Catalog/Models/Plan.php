<?php

namespace App\Domain\Network\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Billing\Models\Subscription;
use Illuminate\Support\Str;

class Plan extends Model
{
    public const STATUSES = ['active', 'inactive'];

    protected $table = 'network_plans';

    protected $fillable = [
        'code', 'name', 'description', 'status', 'monthly_price',
        'annual_price', 'currency', 'included_operations',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'included_operations' => 'integer',
    ];

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = Str::upper(trim($value));
    }

    public function setCurrencyAttribute(string $value): void
    {
        $this->attributes['currency'] = Str::upper(trim($value));
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'network_plan_modules', 'plan_id', 'module_id')
            ->using(PlanModule::class)
            ->withPivot(['is_included', 'limit_value'])
            ->withTimestamps();
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'current_plan_id');
    }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function commercialOffers(): HasMany { return $this->hasMany(\App\Domain\Network\Commerce\Models\NetworkCommercialProduct::class); }
}
