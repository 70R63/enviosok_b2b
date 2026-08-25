<?php
namespace App\Domain\AI\Channels\WhatsApp\Models;use Illuminate\Database\Eloquent\Model;
final class WhatsAppInboundReceipt extends Model{protected$table='ai_whatsapp_inbound_receipts';protected$guarded=['*'];protected$casts=['payload_encrypted'=>'encrypted:array'];}
