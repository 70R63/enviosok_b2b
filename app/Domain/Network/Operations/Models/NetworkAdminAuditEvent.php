<?php
namespace App\Domain\Network\Operations\Models;
use App\Models\User; use Illuminate\Database\Eloquent\Model;
final class NetworkAdminAuditEvent extends Model{public$timestamps=false;protected$fillable=['actor_user_id','action','entity_type','entity_id','before_json','after_json','created_at'];protected$casts=['before_json'=>'array','after_json'=>'array','created_at'=>'datetime'];public function actor(){return$this->belongsTo(User::class,'actor_user_id');}}
