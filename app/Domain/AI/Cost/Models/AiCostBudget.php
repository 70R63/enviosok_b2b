<?php
namespace App\Domain\AI\Cost\Models;
use Illuminate\Database\Eloquent\Model;
final class AiCostBudget extends Model {protected $table='ai_cost_budgets';protected $guarded=[];protected $casts=['budget_usd'=>'decimal:8','budget_microusd'=>'integer'];protected static function booted():void{static::saving(function(self$m):void{if($m->budget_microusd!==null&&(int)$m->budget_microusd<0)throw new \RuntimeException('AI_COST_VALUE_INVALID');if(bccomp((string)($m->budget_usd??0),'0',8)<0)throw new \RuntimeException('AI_COST_VALUE_INVALID');});}}
