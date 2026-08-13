<?php
namespace App\Domain\Shipping\Local\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\HasMany;
final class LocalShippingZone extends Model{public const COVERAGE_MODES=['POSTAL_POOL','POSTAL_MATRIX'];protected$fillable=['tenant_id','code','name','coverage_mode','status'];public function postalCodes():HasMany{return$this->hasMany(LocalShippingZonePostalCode::class,'zone_id');}}
