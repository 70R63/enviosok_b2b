<?php
namespace App\Http\Controllers\Webchat;
use App\Domain\AI\Channels\Webchat\Services\ResolvePublicWebchatChannelService;
use App\Http\Controllers\Controller;
final class HostedWebchatController extends Controller
{
 public function show(string$publicKey,ResolvePublicWebchatChannelService$resolver){$channel=$resolver->resolve($publicKey);return view('ai.webchat.hosted',['publicKey'=>$channel->public_key,'displayName'=>$channel->display_name]);}
 public function widget(){return response()->file(public_path('ai/webchat/widget.js'),['Content-Type'=>'application/javascript; charset=UTF-8','Cache-Control'=>'public, max-age=3600']);}
}
