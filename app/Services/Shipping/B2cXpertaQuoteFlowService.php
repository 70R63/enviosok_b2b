<?php
namespace App\Services\Shipping;
use App\Models\B2cCotizacion;
use App\Services\Shipping\Xperta\{XpertaFrequencyService,XpertaQuoteService};
use RuntimeException;
final class B2cXpertaQuoteFlowService
{
 public function __construct(private XpertaFrequencyService $frequency,private XpertaQuoteService $quotes){}
 public function options(B2cCotizacion $quote):array
 {
  $this->assertStage();
  $coverage=$this->frequency->check((string)$quote->cp_origen,(string)$quote->cp_destino);
  if(!($coverage['available']??false))throw new RuntimeException('La ruta seleccionada no tiene cobertura disponible.');
  return array_map(function(array $option)use($quote){$breakdown=(array)($option['provider_breakdown']??[]);$option['provider_source']='xperta';$option['quote_source']='xperta_estafeta';$option['carrier']='estafeta';$option['provider_extended_area_price']=round((float)($breakdown['costo_ae']??0),2);$option['provider_subtotal']=round((float)($breakdown['sub_total']??0),2);$option['provider_total']=round((float)($breakdown['total']??$option['base_price']??0),2);$option['request_fingerprint']=hash('sha256',implode('|',[$quote->cp_origen,$quote->cp_destino,$quote->peso_facturable?:$quote->peso,$quote->medidas,$option['service_code']??$option['servicio']??'']));$option['correlation_id']=$option['correlation_id']??null;$option['quote_expires_at']=now()->addMinutes(max(1,(int)config('zigo_b2c_xperta.quote_ttl_minutes',30)));return $option;},$this->quotes->options($quote));
 }
 public function assertStage():void
 {
  if(!config('zigo_b2c_xperta.full_flow_enabled',false))throw new RuntimeException('El flujo Xperta B2C está desactivado.');
  if(strtolower((string)config('services.xperta.environment'))!=='stage')throw new RuntimeException('El flujo Xperta B2C solo puede ejecutarse en Stage.');
  if(!config('services.xperta.enabled',false)||!config('services.xperta.frequency_enabled',false))throw new RuntimeException('La configuración Xperta Stage no está completa.');
 }
}
