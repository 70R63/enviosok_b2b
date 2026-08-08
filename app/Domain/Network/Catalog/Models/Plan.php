<?php

namespace App\Domain\Network\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
}
