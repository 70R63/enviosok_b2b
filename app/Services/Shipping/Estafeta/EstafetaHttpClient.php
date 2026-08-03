<?php
namespace App\Services\Shipping\Estafeta;
use App\Services\Shipping\Estafeta\Data\EstafetaOperationResult;
use App\Services\Shipping\Estafeta\Exceptions\EstafetaConfigurationException;
use App\Services\Shipping\Estafeta\Support\EstafetaSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{Http,Log};
use Illuminate\Support\Facades\Schema;
use App\Models\ZigoProviderApiEvent;
use Illuminate\Support\Str;
class EstafetaHttpClient {
 public function request(string $operation,string $method,string $url,array $payload=[],?string $token=null):EstafetaOperationResult {
  if(config('zigo_estafeta.mock_enabled')){if(config('app.env')==='production'||app()->environment('production'))throw new EstafetaConfigurationException('El mock de Estafeta está bloqueado en producción.');return $this->mock($operation);}
  $id=(string)Str::uuid();$started=hrtime(true);$retry=0;$max=max(0,(int)config('zigo_estafeta.retry_times'));
  do{try{$request=Http::acceptJson()->asJson()->timeout((int)config('zigo_estafeta.timeout'))->connectTimeout((int)config('zigo_estafeta.connect_timeout'))->withOptions(['verify'=>(bool)config('zigo_estafeta.verify_ssl')])->withHeaders(['X-Correlation-ID'=>$id,'User-Agent'=>'ZIGO-Estafeta/1.0']);if($token)$request=$request->withToken($token);if(filled(config('zigo_estafeta.api_key')))$request=$request->withHeaders(['X-API-Key'=>config('zigo_estafeta.api_key')]);$response=$request->send(strtoupper($method),$url,['json'=>$payload]);$class=$this->classify($response->status());if(($response->status()===429||$response->serverError())&&$retry<$max){$retry++;usleep((int)config('zigo_estafeta.retry_sleep_ms')*1000);continue;}return $this->finish($operation,$class==='success',$class,$response->status(),(array)($response->json()??[]),$id,$started,$retry);}catch(ConnectionException $e){if($retry<$max){$retry++;continue;}return $this->finish($operation,false,str_contains(strtolower($e->getMessage()),'timed out')?'timeout':'network_error',null,[],$id,$started,$retry);}}while(true);
 }
 private function classify(int $s):string{return match(true){$s>=200&&$s<300=>'success',in_array($s,[401,403],true)=>'authentication_error',$s===422=>'validation_error',$s===429=>'rate_limit',$s>=400&&$s<500=>'business_error',default=>'provider_error'};}
 private function finish(string $op,bool $ok,string $class,?int $status,array $data,string $id,int $started,int $retry):EstafetaOperationResult{$ms=(int)round((hrtime(true)-$started)/1000000);$code=$data['code']??$data['errorCode']??null;$event=EstafetaSanitizer::sanitize(['operation'=>$op,'environment'=>config('zigo_estafeta.environment'),'correlation_id'=>$id,'duration_ms'=>$ms,'status'=>$class,'http_status'=>$status,'provider_code'=>$code,'retry_count'=>$retry]);Log::channel(config('zigo_estafeta.log_channel','estafeta'))->info('Estafeta operation',$event);try{if(Schema::hasTable('zigo_provider_api_events'))ZigoProviderApiEvent::create(['provider'=>'estafeta']+$event+['requested_by_user_id'=>auth()->id(),'metadata'=>[]]);}catch(\Throwable $e){}return new EstafetaOperationResult($ok,$class,$status,$data,$id,$ms,$retry,is_scalar($code)?(string)$code:null);}
 private function mock(string $op):EstafetaOperationResult{$data=match($op){'auth'=>['access_token'=>'mock-token','expires_in'=>3600],'coverage'=>['covered'=>true],'quote'=>['available'=>true,'amount'=>123.45,'currency'=>'MXN'],'shipment'=>['tracking_number'=>'MOCK000000001'],'tracking'=>['status'=>'IN_TRANSIT'],'cancellation'=>['cancelled'=>true],default=>['healthy'=>true]};return new EstafetaOperationResult(true,'success',200,$data,(string)Str::uuid(),0);}
}
