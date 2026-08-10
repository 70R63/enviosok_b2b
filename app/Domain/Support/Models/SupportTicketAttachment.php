<?php
namespace App\Domain\Support\Models; use App\Models\User; use Illuminate\Database\Eloquent\Model;
final class SupportTicketAttachment extends Model{protected $fillable=['ticket_id','message_id','uploaded_by_user_id','original_name','stored_path','mime','size'];public function ticket(){return$this->belongsTo(SupportTicket::class,'ticket_id');}public function message(){return$this->belongsTo(SupportTicketMessage::class,'message_id');}public function uploader(){return$this->belongsTo(User::class,'uploaded_by_user_id');}}
