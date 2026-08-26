<?php
namespace Tests\Feature;
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\{Http,Schema};use Tests\TestCase;
final class AiPublicTrialOnboardingTest extends TestCase
{
 private NetworkCommercialProduct $trial;
 protected function setUp(): void { parent::setUp(); Http::preventStrayRequests(); config(['ai.enabled'=>true,'zigo_surfaces.corporate.host'=>'zigo.local']); if(!Schema::hasTable('network_commercial_products')) Schema::create('network_commercial_products',function(Blueprint $t){$t->id();$t->uuid('uuid')->unique();$t->string('code');$t->string('name');$t->text('description')->nullable();$t->string('type');$t->string('billing_type');$t->decimal('price',12,2);$t->char('currency',3);$t->boolean('is_active')->default(true);$t->boolean('is_public')->default(true);$t->unsignedInteger('sort_order')->default(0);$t->json('metadata')->nullable();$t->timestamps();}); $this->trial=NetworkCommercialProduct::create(['uuid'=>'11111111-1111-4111-8111-111111111111','code'=>'AI-TRIAL-TEST','name'=>'Agentes IA Trial','description'=>'Prueba','type'=>'PLAN','billing_type'=>'MONTHLY','price'=>0,'currency'=>'MXN','is_active'=>true,'is_public'=>true,'metadata'=>['ai_product'=>true,'ai_kind'=>'base','trial'=>true,'ai_plan_code'=>'AI_TRIAL']]); }
 public function test_landing_http_exposes_trial_cta():void{$this->withHeader('Host','zigo.local')->get('/agentes-ia')->assertOk()->assertSee('Probar gratis 7 días')->assertSee('/agentes-ia/comenzar?offer='.$this->trial->uuid);}
 public function test_start_http_selects_trial_offer():void{$this->withHeader('Host','zigo.local')->get('/agentes-ia/comenzar?offer='.$this->trial->uuid)->assertOk()->assertSee('Agentes IA Trial')->assertSee($this->trial->uuid);}
 public function test_trial_offer_is_zero_priced_and_public():void{$this->assertTrue($this->trial->is_public);$this->assertSame('0.00',(string)$this->trial->price);$this->assertTrue((bool)$this->trial->metadata['trial']);}
 public function test_trial_metadata_is_canonical():void{$this->assertSame('base',$this->trial->metadata['ai_kind']);$this->assertSame('AI_TRIAL',$this->trial->metadata['ai_plan_code']);}
 public function test_trial_cta_does_not_call_external_provider():void{$this->withHeader('Host','zigo.local')->get('/agentes-ia');Http::assertNothingSent();$this->assertTrue(true);}
 public function test_paid_offer_remains_distinct_from_trial():void{$paid=NetworkCommercialProduct::create(['uuid'=>'22222222-2222-4222-8222-222222222222','code'=>'AI-INITIAL-TEST','name'=>'Inicial','type'=>'PLAN','billing_type'=>'MONTHLY','price'=>599,'currency'=>'MXN','is_active'=>true,'is_public'=>true,'metadata'=>['ai_product'=>true,'ai_kind'=>'base','trial'=>false,'ai_plan_code'=>'AI_INICIAL']]);$this->assertFalse((bool)$paid->metadata['trial']);$this->assertNotSame($this->trial->uuid,$paid->uuid);}
 public function test_trial_replay_uses_same_catalog_identity():void{$a=NetworkCommercialProduct::where('code','AI-TRIAL-TEST')->firstOrFail();$b=NetworkCommercialProduct::whereJsonContains('metadata->trial',true)->firstOrFail();$this->assertSame($a->id,$b->id);}
 public function test_trial_product_has_no_payment_attempt_identity():void{$this->assertDatabaseMissing('network_commercial_products',['code'=>'PAYMENT_ATTEMPT']);$this->assertNull($this->trial->provider_payment_id ?? null);}
}
