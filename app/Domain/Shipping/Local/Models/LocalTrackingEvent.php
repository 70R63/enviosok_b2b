<?php
namespace App\Domain\Shipping\Local\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class LocalTrackingEvent extends Model{public$updated_at=false;protected$fillable=['local_shipment_id','status','event_code','description','occurred_at','created_by_user_id','metadata'];protected$casts=['occurred_at'=>'datetime','metadata'=>'array'];protected static function booted():void{static::updating(fn()=>throw new \LogicException('Los eventos de tracking son append-only.'));static::deleting(fn()=>throw new \LogicException('Los eventos de tracking son append-only.'));}public function shipment():BelongsTo{return$this->belongsTo(LocalShipment::class,'local_shipment_id');}}
