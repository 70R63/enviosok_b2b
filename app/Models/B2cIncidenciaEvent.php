<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class B2cIncidenciaEvent extends Model
{
    protected $fillable=['incidencia_id','user_id','origin','event_type','previous_status','new_status','previous_assigned_to','new_assigned_to','priority','public_message','internal_note'];
    public function user(){return $this->belongsTo(User::class);}
}
