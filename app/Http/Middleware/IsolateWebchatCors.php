<?php
namespace App\Http\Middleware;
use App\Domain\AI\Channels\Webchat\Models\WebchatChannel;
use App\Domain\AI\Channels\Webchat\Support\WebchatOriginPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
final class IsolateWebchatCors
{
 public function __construct(private WebchatOriginPolicy$origins){}
 public function handle(Request$request,Closure$next):Response
 {
  if(!preg_match('#^api/ai/webchat/(wc_[A-Za-z0-9_-]{43})(?:/|$)#D',$request->path(),$match))return$next($request);
  if($request->isMethod('OPTIONS')){try{$start=preg_match('#/sessions$#D',$request->path())===1;$channel=$this->corsChannel($match[1],!$start);$origin=$request->header('Origin');if(!$this->origins->permits($channel->allowed_origins??[],$origin))return response('',404);}catch(\Throwable){return response('',404);}return$this->allow(response('',204),(string)$origin);}
  $response=$next($request);foreach(['Access-Control-Allow-Origin','Access-Control-Allow-Credentials','Access-Control-Allow-Methods','Access-Control-Allow-Headers','Access-Control-Expose-Headers']as$header)$response->headers->remove($header);
  $origin=$request->header('Origin');try{$start=preg_match('#/sessions$#D',$request->path())===1;$channel=$this->corsChannel($match[1],!$start);if(!$this->origins->permits($channel->allowed_origins??[],$origin))return$response;}catch(\Throwable){return$response;}
  return$this->allow($response,(string)$origin);
 }
 private function allow(Response$response,string$origin):Response{$response->headers->set('Access-Control-Allow-Origin',$origin);$response->headers->set('Vary','Origin');$response->headers->set('Access-Control-Allow-Methods','GET, POST, OPTIONS');$response->headers->set('Access-Control-Allow-Headers','Content-Type, Authorization');return$response;}
 private function corsChannel(string$key,bool$allowRetired):WebchatChannel{$channel=WebchatChannel::query()->where('public_key',$key)->first();if(!$channel&&$allowRetired){$id=DB::table('ai_webchat_channel_keys')->where('public_key',$key)->value('webchat_channel_id');$channel=$id?WebchatChannel::query()->find($id):null;}if(!$channel||!$channel->enabled)throw new \DomainException('channel_unavailable');return$channel;}
}
