<?php
namespace App\Domain\Support\Models; use App\Models\User; use Illuminate\Database\Eloquent\Model;
final class SupportTicketEvent extends Model{public $timestamps=false;protected $fillable=['ticket_id','actor_user_id','type','metadata','created_at'];protected $casts=['metadata'=>'array','created_at'=>'datetime'];public function ticket(){return$this->belongsTo(SupportTicket::class,'ticket_id');}public function actor(){return$this->belongsTo(User::class,'actor_user_id');}}
