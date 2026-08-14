<?php
namespace App\Domain\Network\Tenancy\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class TenantBranding extends Model
{
 protected $table='network_tenant_brandings';protected $fillable=['brand_name','tagline','logo_path','hero_image_path','primary_color','secondary_color','accent_color','favicon_path','support_email','support_phone','landing_cards'];
 protected $casts=['landing_cards'=>'array'];
 public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
}
