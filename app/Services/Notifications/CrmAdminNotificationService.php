<?php
namespace App\Services\Notifications;
use App\Mail\CrmAdminEventMail;use App\Mail\InvoiceRequestRejectedMail;use App\Models\B2cIncidencia;use App\Models\B2cInvoiceRequest;use App\Models\CrmClient;use App\Models\ZigoNotificationDelivery;use Illuminate\Support\Facades\Log;use Illuminate\Support\Facades\Mail;use Illuminate\Support\Facades\URL;use Throwable;
final class CrmAdminNotificationService{
 public function invoice(B2cInvoiceRequest $r):void{$r->loadMissing('user','cotizacion');$cycle=$r->resubmitted_at?->timestamp?:'initial';$this->admin('invoice_request',$r,'invoice_request:'.$r->id.':'.$cycle,[
  'Solicitud'=>'#'.$r->id,'Cliente'=>$r->user?->name,'Correo'=>$r->email_facturacion?:$r->user?->email,'RFC'=>$r->rfc,'Cotización o guía'=>$r->cotizacion_id,'Total'=>number_format((float)$r->monto,2),'Fecha'=>optional($r->solicitada_at)->format('d/m/Y H:i')],route('crm.facturacion.show',$r));}
 public function incident(B2cIncidencia $i):void{$i->loadMissing('user');$this->admin('incident',$i,'incident:'.$i->id,['Folio'=>$i->folio,'Cliente'=>$i->user?->name,'Tipo'=>$i->tipo,'Prioridad'=>$i->prioridad,'Guía o cotización'=>$i->tracking_number?:$i->cotizacion_id,'Fecha'=>$i->created_at?->format('d/m/Y H:i')],route('crm.incidencias.show',$i));}
 public function prospect(CrmClient $c):void{$this->admin('prospect',$c,'prospect:'.$c->id,['Nombre'=>$c->contact_name?:$c->name,'Empresa'=>$c->company_name,'Correo'=>$c->email,'Teléfono'=>$c->phone,'Mensaje o interés'=>mb_substr((string)$c->notes,0,500),'Fecha'=>$c->created_at?->format('d/m/Y H:i')],route('crm.clientes.index',['search'=>$c->email]));}
 public function rejected(B2cInvoiceRequest $r):void{
  $key='invoice_rejected:'.$r->id.':'.optional($r->rejected_at)->timestamp;$email=strtolower(trim((string)($r->email_facturacion?:$r->user?->email)));if(!filter_var($email,FILTER_VALIDATE_EMAIL))return;
  $this->deliver($key,'invoice_rejected',$r,[$email],fn()=>Mail::to($email)->send(new InvoiceRequestRejectedMail($r,URL::temporarySignedRoute('b2c.invoice.correct',now()->addDays(7),['invoiceRequest'=>$r->id]))));
 }
 public function retry(ZigoNotificationDelivery $d):void{$model=$d->notifiable_type::find($d->notifiable_id);if(!$model)return;match($d->event_type){'invoice_request'=>$this->invoice($model),'incident'=>$this->incident($model),'prospect'=>$this->prospect($model),'invoice_rejected'=>$this->rejected($model),default=>null};}
 private function admin(string $type,$model,string $key,array $details,string $url):void{if(!config('zigo_notifications.enabled')||!config('zigo_notifications.'.$type))return;$emails=(array)config('zigo_notifications.emails',[]);if(!$emails)return;$this->deliver($key,$type,$model,$emails,fn()=>Mail::to($emails)->send(new CrmAdminEventMail($type,$details,$url)));}
 private function deliver(string $key,string $type,$model,array $recipients,callable $send):void{
  $delivery=ZigoNotificationDelivery::firstOrCreate(['event_key'=>$key],['event_type'=>$type,'notifiable_type'=>get_class($model),'notifiable_id'=>$model->id,'recipients'=>$recipients]);if($delivery->sent_at)return;
  try{$send();$delivery->forceFill(['status'=>'SENT','attempts'=>$delivery->attempts+1,'sent_at'=>now(),'failed_at'=>null,'last_error'=>null])->save();}
  catch(Throwable $e){$safe=mb_substr(preg_replace('/(token|password|secret|api.?key)\s*[:=]\s*[^\s,;]+/i','$1=[REDACTED]',$e->getMessage()),0,500);$delivery->forceFill(['status'=>'FAILED','attempts'=>$delivery->attempts+1,'failed_at'=>now(),'last_error'=>$safe])->save();Log::warning('Falló notificación ZIGO',['event_type'=>$type,'record_id'=>$model->id,'exception'=>get_class($e)]);}
 }
}
