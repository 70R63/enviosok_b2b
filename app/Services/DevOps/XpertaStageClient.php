<?php

namespace App\Services\DevOps;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class XpertaStageClient
{
    public const TOKEN_CACHE_KEY = 'zigo_devops:xperta_stage:token';
    public const TOKEN_LOCK_KEY = 'zigo_devops:xperta_stage:lock';

    public function configuration(): array { return (array) config('zigo_devops_integrations.xperta_stage', []); }

    public function assertReady(): void
    {
        $c=$this->configuration();
        if (!($c['enabled']??false)) throw new RuntimeException('XPERTA_STAGE_TESTER_DISABLED');
        foreach(['base_url','corporativo','ltd','email','password','api_key','token_minutes','connect_timeout','timeout','token_path','frequency_path','quote_path','services'] as $key) if (blank($c[$key]??null)) throw new RuntimeException('XPERTA_STAGE_CONFIG_MISSING_'.$key);
        $scheme=strtolower((string)parse_url((string)$c['base_url'],PHP_URL_SCHEME)); $host=strtolower((string)parse_url((string)$c['base_url'],PHP_URL_HOST));
        if ($scheme!=='https'||$host==='') throw new RuntimeException('XPERTA_STAGE_HTTPS_REQUIRED');
        if (preg_match('/(^|[.\-])(prod|production|prd)([.\-]|$)/i',$host)) throw new RuntimeException('XPERTA_PRODUCTION_BLOCKED');
    }

    public function freshToken(): array
    {
        $this->assertReady(); $c=$this->configuration(); $minutes=min(10080,max(1,(int)$c['token_minutes']));
        $path=$this->path((string)$c['token_path'],['corporativo'=>$c['corporativo']]);
        $result=$this->request('POST',$path,[],['x-api-key'=>(string)$c['api_key'],'Corporativo'=>(string)$c['corporativo'],'minutos'=>(string)$minutes,'Accept'=>'application/json'],['email'=>$c['email'],'password'=>$c['password'],'minutos'=>$minutes],false);
        if (data_get($result,'data.success')!==true) throw new RuntimeException('XPERTA_TOKEN_INVALID_RESPONSE');
        $token=trim((string)data_get($result,'data.message.token','')); if($token==='')throw new RuntimeException('XPERTA_TOKEN_MISSING');
        $ttl=now()->addMinutes(max(1,$minutes-5)); $expires=data_get($result,'data.message.expires_at'); if(is_string($expires)){try{$candidate=now()->parse($expires)->subMinutes(5);if($candidate->isFuture())$ttl=$candidate;}catch(\Throwable $ignored){}}
        Cache::put(self::TOKEN_CACHE_KEY,$token,$ttl); $result['token']=$token; return $result;
    }

    public function token(): string
    {
        $this->assertReady(); $cached=Cache::get(self::TOKEN_CACHE_KEY); if(is_string($cached)&&$cached!=='')return $cached;
        return Cache::lock(self::TOKEN_LOCK_KEY,10)->block(5,function(){ $again=Cache::get(self::TOKEN_CACHE_KEY); return is_string($again)&&$again!==''?$again:$this->freshToken()['token']; });
    }

    public function frequency(string $origin,string $destination):array
    {
        $this->assertReady();$c=$this->configuration();$path=$this->path((string)$c['frequency_path'],['corporativo'=>$c['corporativo'],'ltd'=>$c['ltd'],'origin'=>$origin,'destination'=>$destination]);
        return $this->request('POST',$path,['token'=>$this->token()],$this->jsonHeaders(),[],true);
    }

    public function quote(array $input):array
    {
        $this->assertReady();$c=$this->configuration();if(!in_array($input['service'],(array)$c['services'],true))throw new RuntimeException('XPERTA_SERVICE_NOT_ALLOWED');
        $path=$this->path((string)$c['quote_path'],['corporativo'=>$c['corporativo'],'ltd'=>$c['ltd'],'service'=>$input['service']]);
        return $this->request('POST',$path,['token'=>$this->token(),'peso'=>(float)$input['weight'],'largo'=>(float)$input['length'],'ancho'=>(float)$input['width'],'alto'=>(float)$input['height'],'cp'=>$input['origin'],'cp_d'=>$input['destination'],'valor_declarado'=>(float)$input['declared_value']],$this->jsonHeaders(),[],true);
    }

    private function request(string $method,string $path,array $json,array $headers,array $query,bool $withBody):array
    {
        $c=$this->configuration();$url=rtrim((string)$c['base_url'],'/').'/'.ltrim($path,'/');$started=microtime(true);
        $request=Http::acceptJson()->connectTimeout(max(1,(int)$c['connect_timeout']))->timeout(max(1,(int)$c['timeout']))->withHeaders($headers)->withOptions(['allow_redirects'=>false]);
        $options=[];if($query!==[])$options['query']=$query;if($withBody)$options['json']=$json;
        $response=$request->send($method,$url,$options);$data=$response->json();
        if(!$response->successful())throw new RuntimeException($this->errorCode($response,$data).' HTTP '.$response->status());
        if(!is_array($data))throw new RuntimeException('XPERTA_INVALID_RESPONSE');
        return ['data'=>$data,'http_status'=>$response->status(),'duration_ms'=>(int)round((microtime(true)-$started)*1000),'correlation_id'=>$this->correlation($response)];
    }
    private function path(string $template,array $values):string {foreach($values as $key=>$value)$template=str_replace('{'.$key.'}',rawurlencode((string)$value),$template);if(preg_match('/\{[^}]+\}/',$template))throw new RuntimeException('XPERTA_STAGE_PATH_INVALID');return $template;}
    private function jsonHeaders():array{$c=$this->configuration();return['Corporativo'=>(string)$c['corporativo'],'x-api-key'=>(string)$c['api_key'],'Content-Type'=>'application/json','Accept'=>'application/json'];}
    private function errorCode(Response $response,$data):string{$status=$response->status();$message=Str::lower(Str::ascii((string)(is_array($data)?data_get($data,'message',''):'')));if($status===403&&str_contains($message,'corporativo'))return'XPERTA_CORPORATE_UNAUTHORIZED';if($status===403&&(str_contains($message,'api key')||str_contains($message,'apikey')))return'XPERTA_API_KEY_UNAUTHORIZED';return'XPERTA_HTTP_'.$status;}
    private function correlation(Response $response):?string{foreach(['X-Correlation-ID','X-Request-ID','Request-ID']as$key){$value=trim((string)$response->header($key));if($value!=='')return mb_substr($value,0,100);}return null;}
}
