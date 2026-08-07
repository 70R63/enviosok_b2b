<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class B2cGuideRecoveryCase extends Model
{
    protected $fillable = ['cotizacion_id','classification','status','original_paid_amount','requote_total','requote_reviewed_at','requote_reviewed_by'];
    protected $casts = ['requote_reviewed_at'=>'datetime','original_paid_amount'=>'decimal:2','requote_total'=>'decimal:2'];
    public function cotizacion(){ return $this->belongsTo(B2cCotizacion::class,'cotizacion_id'); }
}
