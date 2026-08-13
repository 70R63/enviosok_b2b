<?php
namespace App\Domain\Shipping\Local\Models;
use Illuminate\Database\Eloquent\Model;
final class LocalShippingPricingRule extends Model { protected $fillable=['tenant_id','service_id','package_type','from_km','to_km','amount','included_distance_km','overage_price_per_km','overage_rounding','active','valid_from','valid_to','metadata']; protected $casts=['active'=>'boolean','valid_from'=>'datetime','valid_to'=>'datetime','metadata'=>'array','amount'=>'decimal:2','from_km'=>'decimal:3','to_km'=>'decimal:3','included_distance_km'=>'decimal:3','overage_price_per_km'=>'decimal:2']; }
