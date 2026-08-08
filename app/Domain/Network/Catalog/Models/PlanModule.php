<?php
namespace App\Domain\Network\Catalog\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
class PlanModule extends Pivot
{
    protected $table='network_plan_modules';
    public $incrementing=true;
    protected $fillable=['plan_id','module_id','is_included','limit_value'];
    protected $casts=['is_included'=>'boolean','limit_value'=>'integer'];
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function module(): BelongsTo { return $this->belongsTo(Module::class); }
}
