<?php
namespace App\Console\Commands;
use App\Models\ZigoProviderApiEvent;
use App\Services\Shipping\{ProviderStrategyResolver,UnifiedShippingQuoteService};
use App\Services\Shipping\Data\UnifiedQuoteRequest;
use App\Services\Shipping\Diagnostics\ShippingContractRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Schema,Storage};
use Illuminate\Support\Str;
use Throwable;

final class ShippingProbeCommand extends Command {
 protected $signature='zigo:shipping-probe {--environment=stage : stage o production} {--carrier=estafeta : PRD solo acepta estafeta} {--operation=all : PRD solo acepta quote} {--cp-origin=64000} {--cp-destination=64000} {--weight=1} {--length=20} {--width=20} {--height=20} {--package-type=box} {--confirm= : SHIPPING-STAGE o SHIPPING-PRD} {--record-contract : Prohibido en PRD} {--dry-run : No ejecuta tráfico externo}';
 protected $description='Probe técnico no comercial Xperta/Estafeta; PRD requiere bandera, parámetros canónicos y SHIPPING-PRD.';

 public function handle(ProviderStrategyResolver $resolver,UnifiedShippingQuoteService $quotes,ShippingContractRecorder $recorder):int{
  $environment=strtolower((string)$this->option('environment'));
  if(!in_array($environment,['stage','production'],true))return$this->blocked('ENVIRONMENT_INVALID');
  return$environment==='production'?$this->production($resolver,$quotes):$this->stage($resolver,$quotes,$recorder);
 }

 private function production(ProviderStrategyResolver $resolver,UnifiedShippingQuoteService $quotes):int{
  if(!config('services.shipping.prd_quote_probe_enabled',false))return$this->blocked('PRD_PROBE_DISABLED');
  if(strtolower((string)$this->option('carrier'))!=='estafeta')return$this->blocked('CARRIER_NOT_ALLOWED');
  if(strtolower((string)$this->option('operation'))!=='quote')return$this->blocked('OPERATION_NOT_ALLOWED');
  if((string)$this->option('confirm')!=='SHIPPING-PRD')return$this->blocked('CONFIRMATION_INVALID');
  if($this->option('record-contract'))return$this->blocked('CONTRACT_RECORDING_FORBIDDEN');
  if(!$this->canonical())return$this->blocked('NON_CANONICAL_INPUT');
  if($this->option('dry-run'))return$this->productionReport(null,false,'DRY_RUN');
  $decision=$resolver->resolve('quote','estafeta','production',true);
  if(($decision['strategy']??null)!=='xperta_estafeta')return$this->blocked('STRATEGY_NOT_ALLOWED');
  $service=collect((array)config('services.xperta.services',[]))->map(fn($v)=>trim((string)$v))->filter()->first();
  if(!$service)return$this->blocked('XPERTA_SERVICE_MISSING');
  config(['services.xperta.timeout'=>max(1,(int)config('services.shipping.prd_quote_probe_timeout',20))]);
  $request=new UnifiedQuoteRequest('estafeta','64000','64000',1,20,20,20,'box',$service,0);
  $started=hrtime(true);
  try{$result=$quotes->quote($request,'production',true,true)->toArray();$this->event($result,$this->elapsed($started));return$this->productionReport($result,true,null);}
  catch(Throwable $e){$code=$this->errorCode($e);$this->event(['success'=>false,'errorCode'=>$code],$this->elapsed($started));return$this->productionReport(null,true,$code);}
 }

 private function stage(ProviderStrategyResolver $resolver,UnifiedShippingQuoteService $quotes,ShippingContractRecorder $recorder):int{
  $decision=$resolver->resolve('quote',(string)$this->option('carrier'),'stage');$execute=$this->option('confirm')==='SHIPPING-STAGE'&&!$this->option('dry-run');$result=null;
  if($execute&&($decision['strategy']??'unavailable')!=='unavailable'){$request=new UnifiedQuoteRequest('estafeta',(string)$this->option('cp-origin'),(string)$this->option('cp-destination'),(float)$this->option('weight'),(float)$this->option('length'),(float)$this->option('width'),(float)$this->option('height'),(string)$this->option('package-type'));if($this->option('record-contract'))config(['services.shipping.contract_recorder_enabled'=>true]);$result=$recorder->record(['provider'=>$decision['http_provider']??'none','operation'=>'quote','method'=>'POST','caller'=>self::class,'environment'=>'stage','request'=>$request->toArray()],fn()=>$quotes->quote($request,'stage',true)->toArray());}
  $ready=$result!==null&&($result['success']??false)&&($decision['strategy']??null)==='xperta_estafeta';$path=$this->store(['generated_at'=>now()->toIso8601String(),'strategy'=>$decision,'external_call_executed'=>$execute,'dry_run'=>(bool)$this->option('dry-run'),'result'=>$result,'READY_FOR_B2C_QUOTE_ADAPTER'=>$ready]);$this->output($path,$ready);return self::SUCCESS;
 }

 private function productionReport(?array $result,bool $executed,?string $errorCode):int{
  $option=(array)data_get($result,'options.0',[]);$breakdown=(array)($option['provider_breakdown']??[]);$success=(bool)($result['success']??false);
  $report=['generated_at'=>now()->toIso8601String(),'environment'=>'production','strategy'=>'xperta_estafeta','http_provider'=>'xperta','success'=>$success,'external_call_executed'=>$executed,'services'=>collect((array)($result['options']??[]))->pluck('service_code')->filter()->values()->all(),'costo'=>$breakdown['costo']??null,'costo_ae'=>$breakdown['costo_ae']??null,'sub_total'=>$breakdown['sub_total']??null,'total'=>$breakdown['total']??($option['provider_base_price']??null),'currency'=>$option['currency']??null,'correlation_id'=>$result['correlationId']??null,'provider_status'=>$success?'success':($errorCode?'failed':'not_executed'),'READY_FOR_B2C_QUOTE_ADAPTER'=>$success];if($errorCode)$report['error_code']=$errorCode;
  $path=$this->store($report);$this->output($path,$success);return$success||!$executed?self::SUCCESS:self::FAILURE;
 }

 private function canonical():bool{return(string)$this->option('cp-origin')==='64000'&&(string)$this->option('cp-destination')==='64000'&&(float)$this->option('weight')===1.0&&(float)$this->option('length')===20.0&&(float)$this->option('width')===20.0&&(float)$this->option('height')===20.0&&strtolower((string)$this->option('package-type'))==='box';}
 private function blocked(string $code):int{$this->error($code);return self::FAILURE;}
 private function store(array $report):string{$path='private/shipping-probes/probe-'.now()->format('Ymd-His-u').'.json';Storage::disk('local')->put($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return$path;}
 private function output(string $path,bool $ready):void{$this->line('Reporte: storage/app/'.$path);$this->line('READY_FOR_B2C_QUOTE_ADAPTER='.($ready?'true':'false'));}
 private function elapsed(int $started):int{return(int)round((hrtime(true)-$started)/1000000);}
 private function errorCode(Throwable $e):string{$m=strtolower($e->getMessage());if(preg_match('/http\s+(\d{3})/',$m,$match))return'XPERTA_HTTP_'.$match[1];if(str_contains($m,'timed out')||str_contains($m,'timeout'))return'XPERTA_TIMEOUT';return'XPERTA_QUOTE_FAILED';}
 private function event(array $result,int $duration):void{try{if(!Schema::hasTable('zigo_provider_api_events'))return;ZigoProviderApiEvent::create(['provider'=>'xperta','operation'=>'quote_probe','environment'=>'production','correlation_id'=>$result['correlationId']??(string)Str::uuid(),'status'=>($result['success']??false)?'success':'failed','http_status'=>null,'provider_code'=>$result['errorCode']??null,'duration_ms'=>$duration,'retry_count'=>0,'requested_by_user_id'=>null,'metadata'=>['carrier'=>'estafeta','commercial_persistence'=>false]]);}catch(Throwable $ignored){}}
}
