<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class B2cInvoiceRequestEvent extends Model{protected $fillable=['invoice_request_id','user_id','event_type','previous_status','new_status','public_reason','internal_note','replaced_files'];protected $casts=['replaced_files'=>'array'];}
