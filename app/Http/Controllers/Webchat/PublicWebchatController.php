<?php
namespace App\Http\Controllers\Webchat;
use App\Domain\AI\Actions\Enums\ActionRunStatus;
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\AI\Actions\Services\PresentActionConfirmationService;
use App\Domain\AI\Channels\Webchat\Services\{ConfirmWebchatActionService,SendWebchatMessageService,StartWebchatSessionService,WebchatSessionAuthenticator};
use App\Domain\AI\Conversations\Enums\ConversationMessageStatus;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\{JsonResponse,Request};
final class PublicWebchatController extends Controller
{
 public function __construct(private PresentActionConfirmationService$presenter){}
 public function start(Request$r,string$publicKey,WebchatSessionAuthenticator$a,StartWebchatSessionService$s):JsonResponse{$this->only($r,[]);$c=$a->channel($publicKey,$r->header('Origin'));[$session,$token]=$s->start($c);return$this->cors(response()->json(['session_token'=>$token,'expires_at'=>$session->expires_at->toIso8601String(),'branding'=>$this->branding($c)]),$r);}
 public function message(Request$r,string$publicKey,WebchatSessionAuthenticator$a,SendWebchatMessageService$s):JsonResponse{$d=$r->validate(['client_message_id'=>['required','uuid'],'message'=>['required','string']]);$this->only($r,array_keys($d));$c=$a->channel($publicKey,$r->header('Origin'),true);$session=$a->session($c,$this->bearer($r));try{[, $message]=$s->send($c,$session,$d['client_message_id'],$d['message']);}catch(\InvalidArgumentException){return$this->cors(response()->json(['error'=>'message_invalid'],422),$r);}catch(\DomainException){return$this->cors(response()->json(['error'=>'turn_unavailable'],409),$r);}catch(\Throwable){return$this->cors(response()->json(['error'=>'response_unavailable'],503),$r);}return$this->cors(response()->json($this->messagePayload($message,$session->conversation_id)),$r);}
 public function confirm(Request$r,string$publicKey,string$actionRun,WebchatSessionAuthenticator$a,ConfirmWebchatActionService$s):JsonResponse{$this->only($r,[]);$c=$a->channel($publicKey,$r->header('Origin'),true);$session=$a->session($c,$this->bearer($r));try{$message=$s->confirm($c,$session,$actionRun);}catch(\DomainException){return$this->cors(response()->json(['error'=>'confirmation_unavailable'],409),$r);}return$this->cors(response()->json($this->messagePayload($message,$session->conversation_id)),$r);}
 public function history(Request$r,string$publicKey,WebchatSessionAuthenticator$a):JsonResponse{$d=$r->validate(['after_sequence'=>['nullable','integer','min:0']]);$this->only($r,array_keys($d));$c=$a->channel($publicKey,$r->header('Origin'),true);$session=$a->session($c,$this->bearer($r));$messages=ConversationMessage::query()->where('conversation_id',$session->conversation_id)->where('status',ConversationMessageStatus::Completed->value)->where('sequence','>',(int)($d['after_sequence']??0))->orderBy('sequence')->limit(50)->get()->map(fn($m)=>['sequence'=>$m->sequence,'role'=>$m->role->value,'content'=>$m->content]);return$this->cors(response()->json(['messages'=>$messages,'conversation_status'=>$session->conversation->status->value]),$r);}
 public function options(Request$r,string$publicKey,WebchatSessionAuthenticator$a):JsonResponse{$a->channel($publicKey,$r->header('Origin'),true);return$this->cors(response()->json([],204),$r);}
 public function actionOptions(Request$r,string$publicKey,string$actionRun,WebchatSessionAuthenticator$a):JsonResponse{$a->channel($publicKey,$r->header('Origin'),true);return$this->cors(response()->json([],204),$r);}
 private function messagePayload(ConversationMessage$m,int$conversationId):array{$pending=ActionRun::query()->where('conversation_id',$conversationId)->where('status',ActionRunStatus::AwaitingConfirmation->value)->latest()->first();return['assistant_message'=>['sequence'=>$m->sequence,'content'=>$m->content],'conversation_state'=>$m->conversation->status->value,'needs_confirmation'=>$pending!==null,'confirmation'=>$pending?array_merge(['action_run'=>$pending->uuid],$this->presenter->present($pending)):null];}
 private function branding($c):array{return['display_name'=>$c->display_name,'welcome_message'=>$c->welcome_message,'primary_color'=>$c->primary_color,'launcher_label'=>$c->launcher_label];}
 private function only(Request$r,array$allowed):void{if(array_diff(array_keys($r->all()),$allowed)!==[])abort(422,'unsupported_parameter');}
 private function bearer(Request$r):string{$token=$r->bearerToken();if(!is_string($token))abort(404,'session_unavailable');return$token;}
 private function cors(JsonResponse$response,Request$r):JsonResponse{$origin=$r->header('Origin');if($origin)$response->headers->set('Access-Control-Allow-Origin',$origin);$response->headers->set('Vary','Origin');$response->headers->set('Access-Control-Allow-Headers','Content-Type, Authorization');$response->headers->set('Access-Control-Allow-Methods','GET, POST, OPTIONS');return$response;}
}
