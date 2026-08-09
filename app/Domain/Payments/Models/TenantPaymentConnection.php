<?php
namespace App\Domain\Payments\Models;
use App\Domain\Network\Tenancy\Models\Tenant; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Support\Str;
final class TenantPaymentConnection extends Model{
 public const PROVIDER='MERCADO_PAGO'; protected $fillable=['tenant_id','provider','status','provider_account_id','access_token','refresh_token','token_expires_at','scopes','connected_at','disconnected_at','metadata']; protected $hidden=['access_token','refresh_token'];
 protected $casts=['access_token'=>'encrypted','refresh_token'=>'encrypted','scopes'=>'array','metadata'=>'array','token_expires_at'=>'datetime','connected_at'=>'datetime','disconnected_at'=>'datetime'];
 protected static function booted():void{self::creating(fn(self $m)=>$m->uuid??=(string)Str::uuid());} public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);} public function isConnected():bool{return $this->status==='CONNECTED'&&filled($this->access_token);}
}
