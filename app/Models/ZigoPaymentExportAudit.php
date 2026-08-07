<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class ZigoPaymentExportAudit extends Model{protected $fillable=['user_id','tab','scope','filters','record_count','filename'];protected $casts=['filters'=>'array'];}
