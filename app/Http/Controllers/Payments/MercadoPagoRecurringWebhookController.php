<?php
namespace App\Http\Controllers\Payments;
use App\Domain\Network\Billing\RecurringSubscriptionService;
use App\Domain\Network\Billing\Contracts\RecurringSubscriptionProvider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
final class MercadoPagoRecurringWebhookController
{
 public function __invoke(Request $request,RecurringSubscriptionService $service):JsonResponse
 {
  $id=(string)($request->input('data.id')?:$request->input('id')); if($id==='')return response()->json(['received'=>true]); abort_unless(app(RecurringSubscriptionProvider::class)->validateWebhook($request,$id),401); $key=(string)($request->header('x-request-id')?:'recurring:'.$id.':'.(string)$request->input('type','event')); $type=strtolower((string)($request->input('type')?:$request->input('topic')?:$request->input('action'))); if(str_contains($type,'payment'))$service->paymentWebhook($id,$key); else $service->webhook($id,$key); return response()->json(['received'=>true]);
 }
}
