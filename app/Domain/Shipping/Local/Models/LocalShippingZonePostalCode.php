<?php
namespace App\Domain\Shipping\Local\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class LocalShippingZonePostalCode extends Model{protected$table='local_shipping_zone_postal_codes';protected$fillable=['tenant_id','zone_id','postal_code','state','municipality','active','valid_from','valid_to','metadata'];protected$casts=['active'=>'boolean','valid_from'=>'datetime','valid_to'=>'datetime','metadata'=>'array'];public function zone():BelongsTo{return$this->belongsTo(LocalShippingZone::class,'zone_id');}}
