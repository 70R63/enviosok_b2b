<?php
namespace App\Domain\Shipping\Local\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class LocalShippingZonePostalCode extends Model{protected$table='local_shipping_zone_postal_codes';protected$fillable=['zone_id','postal_code'];public function zone():BelongsTo{return$this->belongsTo(LocalShippingZone::class,'zone_id');}}
