<?php
namespace App\Domain\Network\Billing\Models;
use App\Domain\Network\Catalog\Models\Module;use App\Domain\Network\Tenancy\Models\Tenant;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class Entitlement extends Model{public const SOURCES=['plan','override'];protected$table='network_entitlements';protected$fillable=['subscription_id','tenant_id','module_id','code','is_enabled','limit_value','source'];protected$casts=['is_enabled'=>'boolean','limit_value'=>'integer'];public function subscription():BelongsTo{return$this->belongsTo(Subscription::class);}public function tenant():BelongsTo{return$this->belongsTo(Tenant::class);}public function module():BelongsTo{return$this->belongsTo(Module::class);}}
