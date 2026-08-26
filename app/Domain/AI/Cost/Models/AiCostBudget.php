<?php
namespace App\Domain\AI\Cost\Models;
use Illuminate\Database\Eloquent\Model;
final class AiCostBudget extends Model {protected $table='ai_cost_budgets';protected $guarded=[];protected $casts=['budget_usd'=>'decimal:8','budget_microusd'=>'integer'];}
