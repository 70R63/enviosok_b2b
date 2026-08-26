<?php
namespace App\Domain\AI\Cost\Models;
use Illuminate\Database\Eloquent\Model;
final class AiCostReservation extends Model {protected $table='ai_cost_reservations';protected $guarded=[];protected $casts=['rate_snapshot'=>'array','reserved_microusd'=>'integer','actual_cost_microusd'=>'integer','released_microusd'=>'integer'];}
