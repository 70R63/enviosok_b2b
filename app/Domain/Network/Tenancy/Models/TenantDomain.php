<?php
namespace App\Domain\Network\Tenancy\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class TenantDomain extends Model
{
 public const TYPES=['subdomain','custom'];public const ENVIRONMENTS=['production','sandbox'];public const STATUSES=['pending','verified','disabled'];
 protected $table='network_tenant_domains';protected $fillable=['tenant_id','domain','type','environment','is_primary','status','verified_at'];protected $casts=['is_primary'=>'boolean','verified_at'=>'datetime'];
 public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
 public function setDomainAttribute(string $value):void{$this->attributes['domain']=self::normalize($value);}
 public static function normalize(string $host):string{$host=trim(strtolower($host));$parsed=parse_url(str_contains($host,'://')?$host:'//'.$host,PHP_URL_HOST);return rtrim((string)$parsed,'.');}
}
