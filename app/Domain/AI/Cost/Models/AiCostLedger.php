<?php
namespace App\Domain\AI\Cost\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Support\Str;
final class AiCostLedger extends Model {protected $table='ai_cost_ledger';protected $guarded=[];protected $casts=['cost_usd'=>'decimal:8','input_tokens'=>'integer','cached_input_tokens'=>'integer','output_tokens'=>'integer','total_tokens'=>'integer'];protected static function booted():void{static::creating(fn(self$m)=>$m->uuid??=(string)Str::uuid());}}
