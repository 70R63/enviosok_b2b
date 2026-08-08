<?php
namespace App\Domain\Shipping\Local\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class LocalShippingService extends Model{public const LEVELS=['same_day','next_day','scheduled'];protected$fillable=['code','name','origin_zone_id','destination_zone_id','service_level','base_cost','base_price','currency','max_weight','max_length','max_width','max_height','estimated_min_hours','estimated_max_hours','status'];protected$casts=['base_cost'=>'decimal:2','base_price'=>'decimal:2'];public function originZone():BelongsTo{return$this->belongsTo(LocalShippingZone::class,'origin_zone_id');}public function destinationZone():BelongsTo{return$this->belongsTo(LocalShippingZone::class,'destination_zone_id');}}
