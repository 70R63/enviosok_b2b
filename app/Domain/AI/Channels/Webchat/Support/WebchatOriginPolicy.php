<?php
namespace App\Domain\AI\Channels\Webchat\Support;
final class WebchatOriginPolicy
{
    public function canonicalize(string $origin, bool $allowLocalhost=false):string
    {
        $origin=trim($origin);$parts=parse_url($origin);
        if(!is_array($parts)||array_intersect(array_keys($parts),['user','pass','path','query','fragment'])!==[]||!isset($parts['scheme'],$parts['host']))throw new \InvalidArgumentException('Invalid Webchat origin.');
        $scheme=strtolower((string)$parts['scheme']);$host=strtolower(rtrim((string)$parts['host'],'.'));
        if($scheme!=='https'&&!($allowLocalhost&&$scheme==='http'&&in_array($host,['localhost','127.0.0.1','::1'],true)))throw new \InvalidArgumentException('Webchat origins must use HTTPS.');
        if(!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)&&!filter_var($host,FILTER_VALIDATE_IP)&&$host!=='localhost')throw new \InvalidArgumentException('Invalid Webchat origin host.');
        $port=isset($parts['port'])?(int)$parts['port']:null;if($port!==null&&($port<1||$port>65535))throw new \InvalidArgumentException('Invalid Webchat origin port.');
        return $scheme.'://'.$host.($port!==null?':'.$port:'');
    }
    public function permits(array $allowed,?string $origin,bool $hosted=false):bool
    {
        if($hosted&&$origin===null)return true;if(!is_string($origin)||$origin==='')return false;
        try{$canonical=$this->canonicalize($origin,app()->environment(['local','testing']));}catch(\Throwable){return false;}
        $firstParty=null;try{$firstParty=$this->canonicalize((string)config('app.url'),app()->environment(['local','testing']));}catch(\Throwable){}
        return $canonical===$firstParty||in_array($canonical,$allowed,true);
    }
}
