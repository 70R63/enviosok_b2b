<?php
namespace App\Services\Shipping\Diagnostics;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
final class ShippingContractRecorder {
 private const SECRET=['authorization','token','password','client_secret','api_key','x-api-key','account','customer_number','email','phone','address','label','data'];
 public function enabled():bool{if($this->production()&&!config('services.shipping.contract_recorder_allow_production',false))return false;return (bool)config('services.shipping.contract_recorder_enabled',false);}
 public function record(array $context,callable $call):mixed{if($this->production()&&!config('services.shipping.contract_recorder_allow_production',false))throw new RuntimeException('ShippingContractRecorder está bloqueado en production.');$id=(string)($context['correlation_id']??Str::uuid());$started=hrtime(true);try{$response=$call();$status=(int)($context['http_status']??200);$this->write($context,$response,$status,$id,$started);return$response;}catch(\Throwable $e){$this->write($context,[],(int)($context['http_status']??0),$id,$started);throw$e;}}
 public function describe(array $value,int $depth=0):array{if($depth>5)return['type'=>'truncated'];$out=[];foreach($value as $key=>$item){$name=(string)$key;if($this->secret($name)){$out[$name]=['type'=>'redacted'];continue;}$out[$name]=is_array($item)?['type'=>'object','keys'=>$this->describe($item,$depth+1)]:['type'=>get_debug_type($item)];}return$out;}
 private function write(array $c,mixed $response,int $status,string $id,int $started):void{if(!$this->enabled())return;$url=(string)($c['url']??'');$report=['provider'=>$c['provider']??'unknown','operation'=>$c['operation']??'unknown','method'=>strtoupper((string)($c['method']??'GET')),'host'=>parse_url($url,PHP_URL_HOST),'path'=>parse_url($url,PHP_URL_PATH),'query_param_names'=>array_keys((array)($c['query']??[])),'header_names'=>array_values(array_filter(array_keys((array)($c['headers']??[])),fn($v)=>!$this->secret((string)$v))),'request_structure'=>$this->describe((array)($c['request']??[])),'response_structure'=>$this->describe(is_array($response)?$response:[]),'http_status'=>$status,'duration_ms'=>(int)round((hrtime(true)-$started)/1000000),'correlation_id'=>$id,'caller'=>$c['caller']??null,'environment'=>$c['environment']??app()->environment()];Storage::disk('local')->put('private/shipping-contract-recordings/'.$id.'.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}
 private function secret(string $key):bool{$key=strtolower($key);foreach(self::SECRET as $secret)if(str_contains($key,$secret))return true;return false;}
 private function production():bool{return strtolower((string)config('app.env'))==='production'||app()->environment('production');}
}
