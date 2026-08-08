<?php

namespace App\Domain\Network\Tenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Network\Catalog\Models\Plan;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive', 'suspended'];

    protected $table = 'network_tenants';

    protected $fillable = ['name', 'slug', 'status', 'current_plan_id'];

    protected $casts = ['current_plan_id' => 'integer'];

    /** Current commercial plan assignment; deliberately not a subscription. */
    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            $tenant->uuid ??= (string) Str::uuid();
        });
    }

    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = Str::slug(Str::lower($value));
    }
}
