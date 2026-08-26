<?php
namespace App\Domain\Network\Billing;
use App\Domain\Network\Billing\Contracts\RecurringSubscriptionProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
final class MercadoPagoRecurringSubscriptionProvider implements RecurringSubscriptionProvider
{
 private function request(string $method,string $path,array $payload=[]):array
 {
  $token=(string)config('zigo_payments.platform.access_token'); if($token==='')throw new RuntimeException('RECURRING_PROVIDER_CONFIGURATION_MISSING');
  $r=Http::acceptJson()->withToken($token)->{$method}(rtrim((string)config('zigo_payments.providers.mercado_pago.api_url'),'/' ).$path,$payload);
  if(!$r->successful())throw new RuntimeException('RECURRING_PROVIDER_HTTP_'.$r->status()); return (array)$r->json();
 }
 public function createPlan(array $payload):array{return$this->request('post','/preapproval_plan',$payload);}
 public function createSubscription(array $payload):array{return$this->request('post','/preapproval',$payload);}
 public function retrieve(string $id):array{return$this->request('get','/preapproval/'.rawurlencode($id));}
 public function update(string $id,array $payload):array{return$this->request('put','/preapproval/'.rawurlencode($id),$payload);}
 public function retrievePayment(string $id):array{return$this->request('get','/v1/payments/'.rawurlencode($id));}
 public function validateWebhook(\Illuminate\Http\Request $request,string $id):bool
 {
  $secret=(string)config('zigo_payments.platform.webhook_secret'); $sig=(string)$request->header('x-signature'); $rid=(string)$request->header('x-request-id'); preg_match('/(?:^|,)\s*ts=([^,]+)/',$sig,$ts); preg_match('/(?:^|,)\s*v1=([^,]+)/',$sig,$v1); if(!$secret||!$rid||!isset($ts[1],$v1[1])||!ctype_digit($ts[1]))return false; $manifest='id:'.strtolower(trim($id)).';request-id:'.$rid.';ts:'.$ts[1].';'; return hash_equals(hash_hmac('sha256',$manifest,$secret),trim($v1[1]));
 }
}
