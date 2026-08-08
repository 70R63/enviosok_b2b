<?php
namespace App\Domain\Network\Tenancy\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class TenantBranding extends Model
{
 protected $table='network_tenant_brandings';protected $fillable=['brand_name','logo_path','primary_color','secondary_color','accent_color','favicon_path','support_email','support_phone'];
 public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
}
