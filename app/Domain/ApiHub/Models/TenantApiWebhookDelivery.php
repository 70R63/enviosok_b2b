<?php
namespace App\Domain\ApiHub\Models;use Illuminate\Database\Eloquent\Model;
final class TenantApiWebhookDelivery extends Model{protected $fillable=['event_id','tenant_id','api_client_id','webhook_endpoint_id','event_type','resource_type','resource_uuid','payload','attempt','status','http_status','next_retry_at','delivered_at','last_error_safe'];protected $casts=['payload'=>'array','next_retry_at'=>'datetime','delivered_at'=>'datetime'];public function endpoint(){return$this->belongsTo(TenantApiWebhookEndpoint::class,'webhook_endpoint_id');}}
