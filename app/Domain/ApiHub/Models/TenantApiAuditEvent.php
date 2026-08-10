<?php
namespace App\Domain\ApiHub\Models;use Illuminate\Database\Eloquent\Model;
final class TenantApiAuditEvent extends Model{public $timestamps=false;protected $fillable=['tenant_id','api_client_id','api_key_id','actor_user_id','action','metadata','created_at'];protected $casts=['metadata'=>'array','created_at'=>'datetime'];}
