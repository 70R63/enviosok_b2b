<?php
namespace App\Domain\Support\Models; use App\Models\User; use Illuminate\Database\Eloquent\Model;
final class SupportTicketMessage extends Model{protected $fillable=['ticket_id','author_user_id','visibility','message'];public function ticket(){return$this->belongsTo(SupportTicket::class,'ticket_id');}public function author(){return$this->belongsTo(User::class,'author_user_id');}public function attachments(){return$this->hasMany(SupportTicketAttachment::class,'message_id');}}
