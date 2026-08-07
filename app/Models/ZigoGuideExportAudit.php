<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class ZigoGuideExportAudit extends Model{protected $fillable=['user_id','scope','filters','record_count','filename'];protected $casts=['filters'=>'array'];}
