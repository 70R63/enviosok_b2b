<?php

namespace App\Domain\Network\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Module extends Model
{
    public const TYPES = ['channel', 'core', 'addon', 'integration'];

    protected $table = 'network_modules';

    protected $fillable = ['code', 'name', 'description', 'type', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = Str::upper(trim($value));
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'network_plan_modules', 'module_id', 'plan_id')
            ->using(PlanModule::class)
            ->withPivot(['is_included', 'limit_value'])
            ->withTimestamps();
    }
}
