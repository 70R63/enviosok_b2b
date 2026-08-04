<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class ZigoDevOpsAudit extends Model {protected $table='zigo_devops_audits';public const UPDATED_AT=null;protected $fillable=['user_id','action','environment','deployment_id','result','ip_hash','user_agent_hash','metadata','created_at'];protected $casts=['metadata'=>'array','created_at'=>'datetime'];}
