<?php
namespace Tests\Feature;
use App\Models\B2cCotizacion;use App\Services\Shipping\B2cXpertaQuoteFlowService;use App\Services\Shipping\Xperta\{XpertaFrequencyService,XpertaQuoteService};use Mockery;use RuntimeException;use Tests\TestCase;
final class B2cXpertaStageQuoteFlowTest extends TestCase
{
 public function test_stage_frequency_and_quote_are_normalized_without_commercial_price_leak():void
 {
  config(['zigo_b2c_xperta.full_flow_enabled'=>true,'zigo_b2c_xperta.quote_ttl_minutes'=>30,'services.xperta.environment'=>'stage','services.xperta.enabled'=>true,'services.xperta.frequency_enabled'=>true]);
  $quote=new B2cCotizacion(['cp_origen'=>'64000','cp_destino'=>'64000','peso'=>1,'peso_facturable'=>1,'medidas'=>'20x20x20']);
  $frequency=Mockery::mock(XpertaFrequencyService::class);$frequency->shouldReceive('check')->once()->with('64000','64000')->andReturn(['available'=>true]);
  $quotes=Mockery::mock(XpertaQuoteService::class);$quotes->shouldReceive('options')->once()->andReturn([['logistico'=>'Estafeta','servicio'=>'Terrestre','service_code'=>'terrestre','base_price'=>116,'provider_breakdown'=>['costo'=>100,'costo_ae'=>10,'sub_total'=>110,'total'=>116]]]);
  $option=(new B2cXpertaQuoteFlowService($frequency,$quotes))->options($quote)[0];
  $this->assertSame('xperta_estafeta',$option['quote_source']);$this->assertSame(10.0,$option['provider_extended_area_price']);$this->assertSame(110.0,$option['provider_subtotal']);$this->assertArrayNotHasKey('precio',$option);
 }
 public function test_disabled_flag_keeps_provider_untouched():void
 {
  config(['zigo_b2c_xperta.full_flow_enabled'=>false,'services.xperta.environment'=>'stage']);$this->expectException(RuntimeException::class);$this->expectExceptionMessage('desactivado');
  (new B2cXpertaQuoteFlowService(Mockery::mock(XpertaFrequencyService::class),Mockery::mock(XpertaQuoteService::class)))->options(new B2cCotizacion());
 }
 public function test_production_is_blocked_before_provider_calls():void
 {
  config(['zigo_b2c_xperta.full_flow_enabled'=>true,'services.xperta.environment'=>'production','services.xperta.enabled'=>true,'services.xperta.frequency_enabled'=>true]);$frequency=Mockery::mock(XpertaFrequencyService::class);$frequency->shouldNotReceive('check');$quotes=Mockery::mock(XpertaQuoteService::class);$quotes->shouldNotReceive('options');$this->expectException(RuntimeException::class);$this->expectExceptionMessage('solo puede ejecutarse en Stage');(new B2cXpertaQuoteFlowService($frequency,$quotes))->options(new B2cCotizacion());
 }
}
