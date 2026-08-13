<?php
namespace App\Domain\Shipping\Local\Models;
use Illuminate\Database\Eloquent\Model;
final class LocalShippingPackageRule extends Model { protected $fillable=['tenant_id','service_id','package_type','max_weight_kg','max_dimension_1_cm','max_dimension_2_cm','max_dimension_3_cm','active']; protected $casts=['active'=>'boolean','max_weight_kg'=>'decimal:2','max_dimension_1_cm'=>'decimal:2','max_dimension_2_cm'=>'decimal:2','max_dimension_3_cm'=>'decimal:2']; }
