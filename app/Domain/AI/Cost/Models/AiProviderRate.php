<?php
namespace App\Domain\AI\Cost\Models;
use Illuminate\Database\Eloquent\Model;
final class AiProviderRate extends Model {protected $table='ai_provider_rates';protected $guarded=[];protected $casts=['effective_from'=>'datetime','enabled'=>'boolean','input_cost_per_million'=>'decimal:8','cached_input_cost_per_million'=>'decimal:8','output_cost_per_million'=>'decimal:8'];}
