<?php
namespace App\Domain\ApiHub\Models;use Illuminate\Database\Eloquent\Model;
final class TenantApiUsageEvent extends Model{public $timestamps=false;protected $fillable=['tenant_id','api_client_id','api_key_id','request_id','endpoint_code','units','http_status','duration_ms','occurred_at','created_at'];protected $casts=['occurred_at'=>'datetime','created_at'=>'datetime'];}
