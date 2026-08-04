<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ZigoProviderApiEvent extends Model
{
    protected $table='zigo_provider_api_events';
    protected $fillable=['provider','operation','environment','correlation_id','status','http_status','provider_code','duration_ms','retry_count','requested_by_user_id','metadata'];
    protected $casts=['metadata'=>'array','http_status'=>'integer','duration_ms'=>'integer','retry_count'=>'integer'];
}
