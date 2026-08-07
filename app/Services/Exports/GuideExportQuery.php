<?php
namespace App\Services\Exports;use App\Models\B2cCotizacion;use Illuminate\Database\Eloquent\Builder;use Illuminate\Http\Request;
final class GuideExportQuery{
 public function filters(Request $r):array{return array_filter($r->validate(['search'=>'nullable|string|max:191','cliente'=>'nullable|string|max:191','empresa'=>'nullable|string|max:191','carrier'=>'nullable|string|max:80','servicio'=>'nullable|string|max:80','estado'=>'nullable|string|max:80','fecha_desde'=>'nullable|date','fecha_hasta'=>'nullable|date|after_or_equal:fecha_desde','waybill'=>'nullable|string|max:191','tracking'=>'nullable|string|max:191','payment_status'=>'nullable|string|max:50','guia_estatus'=>'nullable|string|max:80']),fn($v)=>$v!==null&&$v!=='');}
 public function make(array $f=[]):Builder{$q=B2cCotizacion::query()->with(['user:id,name,email,empresa_id','user.empresa:id,nombre','invoiceRequest:id,cotizacion_id,status,payment_method'])->where(function($q){$q->whereNotNull('guia_id')->orWhereNotNull('tracking_number')->orWhereNotNull('guia_estatus');});
  foreach(['carrier'=>'carrier','servicio'=>'service_code','estado'=>'estatus','payment_status'=>'payment_status','guia_estatus'=>'guia_estatus'] as $key=>$column)if(isset($f[$key]))$q->where($column,$f[$key]);
  if(isset($f['waybill']))$q->where('guia_id','like','%'.$f['waybill'].'%');if(isset($f['tracking']))$q->where('tracking_number','like','%'.$f['tracking'].'%');
  if(isset($f['fecha_desde']))$q->whereDate('created_at','>=',$f['fecha_desde']);if(isset($f['fecha_hasta']))$q->whereDate('created_at','<=',$f['fecha_hasta']);
  if(isset($f['cliente']))$q->whereHas('user',fn($u)=>$u->where('name','like','%'.$f['cliente'].'%')->orWhere('email','like','%'.$f['cliente'].'%'));
  if(isset($f['empresa']))$q->whereHas('user.empresa',fn($e)=>$e->where('nombre','like','%'.$f['empresa'].'%'));
  if(isset($f['search'])){$v=$f['search'];$q->where(function($x)use($v){$x->where('referencia','like','%'.$v.'%')->orWhere('guia_id','like','%'.$v.'%')->orWhere('tracking_number','like','%'.$v.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$v.'%')->orWhere('email','like','%'.$v.'%'));if(ctype_digit($v))$x->orWhereKey((int)$v);});}
  return $q;
 }
}
