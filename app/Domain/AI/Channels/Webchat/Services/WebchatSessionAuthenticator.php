<?php
namespace App\Domain\AI\Channels\Webchat\Services;
use App\Domain\AI\Channels\Webchat\Models\{WebchatChannel,WebchatSession};
use App\Domain\AI\Channels\Webchat\Support\WebchatOriginPolicy;
final class WebchatSessionAuthenticator
{
 public function __construct(private ResolvePublicWebchatChannelService$resolver,private WebchatOriginPolicy$origins){}
 public function channel(string$key,?string$origin,bool$allowRetiredKey=false):WebchatChannel{$channel=$this->resolver->resolve($key,$allowRetiredKey);if(!$this->origins->permits($channel->allowed_origins??[],$origin))throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('channel_unavailable');return$channel;}
 public function session(WebchatChannel$channel,string$token):WebchatSession{if(!preg_match('/^[A-Za-z0-9_-]{43}$/D',$token))throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('session_unavailable');$session=WebchatSession::query()->where('tenant_id',$channel->tenant_id)->where('webchat_channel_id',$channel->id)->where('token_hash',hash('sha256',$token))->firstOrFail();if(!$session->isAvailable())throw new \Symfony\Component\HttpKernel\Exception\GoneHttpException('session_expired');return$session;}
}
