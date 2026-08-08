<?php
namespace App\Domain\Shipping\Local\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\HasMany;
final class LocalShippingZone extends Model{protected$fillable=['code','name','status'];public function postalCodes():HasMany{return$this->hasMany(LocalShippingZonePostalCode::class,'zone_id');}}
