<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class ZigoNotificationDelivery extends Model{protected $fillable=['event_key','event_type','notifiable_type','notifiable_id','recipients','status','attempts','sent_at','failed_at','last_error'];protected $casts=['recipients'=>'array','sent_at'=>'datetime','failed_at'=>'datetime'];}
