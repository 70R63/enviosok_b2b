<?php
namespace App\Domain\AI\Channels\WhatsApp\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Support\Str;
final class WhatsAppDelivery extends Model{protected$table='ai_whatsapp_deliveries';protected$guarded=['*'];protected$casts=['confirmation_expires_at'=>'datetime','confirmed_at'=>'datetime'];protected static function booted():void{static::creating(fn(self$m)=>$m->uuid??=(string)Str::uuid());}}
