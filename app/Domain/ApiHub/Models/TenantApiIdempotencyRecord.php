<?php
namespace App\Domain\ApiHub\Models;use Illuminate\Database\Eloquent\Model;
final class TenantApiIdempotencyRecord extends Model{protected $fillable=['tenant_id','api_client_id','endpoint_code','idempotency_key','request_fingerprint','response_status','response_body','expires_at'];protected $casts=['response_body'=>'array','expires_at'=>'datetime'];}
