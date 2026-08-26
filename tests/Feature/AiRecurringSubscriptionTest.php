<?php
namespace Tests\Feature;
use App\Domain\Network\Billing\MercadoPagoRecurringSubscriptionProvider;
use Illuminate\Support\Facades\{Http,Config};
use Tests\TestCase;
final class AiRecurringSubscriptionTest extends TestCase
{
 protected function setUp():void{parent::setUp();Http::preventStrayRequests();Config::set(['zigo_payments.platform.access_token'=>'test-token','zigo_payments.providers.mercado_pago.api_url'=>'https://api.mercadopago.com']);}
 public function test_monthly_plan_payload_uses_one_month_and_persists_provider_response():void
 {Http::fake(['https://api.mercadopago.com/preapproval_plan'=>Http::response(['id'=>'plan-monthly'],201)]);$r=app(MercadoPagoRecurringSubscriptionProvider::class)->createPlan(['reason'=>'Inicial','auto_recurring'=>['frequency'=>1,'frequency_type'=>'months','transaction_amount'=>599.0,'currency_id'=>'MXN'],'back_url'=>'https://zigo.local/agentes-ia']);$this->assertSame('plan-monthly',$r['id']);Http::assertSent(fn($q)=>$q->url()==='https://api.mercadopago.com/preapproval_plan'&&$q['auto_recurring']['frequency']===1&&$q['auto_recurring']['frequency_type']==='months');}
 public function test_annual_subscription_payload_uses_twelve_month_frequency_and_external_reference():void
 {Http::fake(['https://api.mercadopago.com/preapproval'=>Http::response(['id'=>'sub-annual','status'=>'pending'],201)]);$r=app(MercadoPagoRecurringSubscriptionProvider::class)->createSubscription(['preapproval_plan_id'=>'plan-annual','external_reference'=>'ai-sub:test','payer_email'=>'owner@example.test','back_url'=>'https://zigo.local/admin/plan']);$this->assertSame('sub-annual',$r['id']);Http::assertSent(fn($q)=>$q['preapproval_plan_id']==='plan-annual'&&$q['external_reference']==='ai-sub:test');}
}
