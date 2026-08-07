<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class B2cGuideRecoveryAudit extends Model
{
    protected $fillable = ['cotizacion_id','actor_user_id','action','result','correlation_id','ip_hash','metadata'];
    protected $casts = ['metadata'=>'array'];
}
