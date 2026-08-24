<?php

namespace Tests\Feature;

use App\Domain\AI\Actions\ActionRegistry;
use App\Domain\AI\Actions\Enums\{ActionConfirmationPolicy,ActionEffect};
use App\Domain\Shipping\AI\Actions\{CreateShipmentGuideActionHandler,QuoteShipmentActionHandler,TrackShipmentActionHandler};
use Illuminate\Support\Facades\Http;
use App\Domain\AI\Actions\Support\ActionSchemaValidator;
use Tests\TestCase;

final class ZigoAiLogisticsActionsTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); Http::preventStrayRequests(); }

    public function test_registry_contains_exactly_three_trusted_zigo_actions(): void
    {
        $definitions=app(ActionRegistry::class)->all();
        $this->assertSame(['zigo.quote_shipment','zigo.track_shipment','zigo.create_shipment_guide'],array_keys($definitions));
        $this->assertInstanceOf(QuoteShipmentActionHandler::class,$definitions['zigo.quote_shipment']->handler);
        $this->assertSame(ActionEffect::Read,$definitions['zigo.quote_shipment']->effect);
        $this->assertSame(ActionConfirmationPolicy::None,$definitions['zigo.quote_shipment']->confirmation);
        $this->assertSame(['SHIPPING'],$definitions['zigo.quote_shipment']->requiredEntitlements);
        $this->assertInstanceOf(TrackShipmentActionHandler::class,$definitions['zigo.track_shipment']->handler);
        $this->assertSame(['TRACKING'],$definitions['zigo.track_shipment']->requiredEntitlements);
        $this->assertInstanceOf(CreateShipmentGuideActionHandler::class,$definitions['zigo.create_shipment_guide']->handler);
        $this->assertSame(ActionEffect::Write,$definitions['zigo.create_shipment_guide']->effect);
        $this->assertSame(ActionConfirmationPolicy::Required,$definitions['zigo.create_shipment_guide']->confirmation);
    }

    public function test_handlers_delegate_to_real_boundaries_without_direct_provider_io(): void
    {
        $quote=file_get_contents(app_path('Domain/Shipping/AI/Actions/QuoteShipmentActionHandler.php'));
        $track=file_get_contents(app_path('Domain/Shipping/AI/Actions/TrackShipmentActionHandler.php'));
        $guide=file_get_contents(app_path('Domain/Shipping/AI/Actions/CreateShipmentGuideActionHandler.php'));
        $this->assertStringContainsString('TenantB2cQuoteService',$quote);
        $this->assertStringContainsString('LocalTrackingService',$track);
        $this->assertStringContainsString('TenantOperationService',$guide);
        $this->assertStringContainsString('LocalShipmentService',$guide);
        $this->assertStringContainsString('LocalShippingQuoteSnapshot',$guide);
        foreach([$quote,$track,$guide]as$source){$this->assertStringNotContainsString('Http::',$source);$this->assertStringNotContainsString('Xperta',$source);$this->assertStringNotContainsString('Estafeta',$source);$this->assertStringContainsString('DB::transactionLevel() !== 0',$source);}
    }

    public function test_guide_schema_rejects_model_price_and_provider_authority(): void
    {
        $definition=app(ActionRegistry::class)->find('zigo.create_shipment_guide');
        foreach(['price','amount','provider','carrier','service_code','tenant_id']as$field)$this->assertArrayNotHasKey($field,$definition->inputSchema['properties']);
        $this->assertSame(['quote_reference','option_reference','sender','recipient','customer_reference'],$definition->inputSchema['required']);
        foreach(['provider_cost','margin','profit','raw_response','internal_id']as$field)$this->assertArrayNotHasKey($field,$definition->outputSchema['properties']);
    }

    public function test_allowed_actions_ui_is_checkbox_only_and_escaped(): void
    {
        $view=file_get_contents(resource_path('views/tenant/admin/ai-agents/show.blade.php'));
        $controller=file_get_contents(app_path('Http/Controllers/Tenant/AiAgentController.php'));
        $this->assertStringContainsString('type="checkbox"',$view);
        $this->assertStringContainsString('availableActionDefinitions',$view);
        $this->assertStringNotContainsString('type="text" name="action_key"',$view);
        $this->assertStringContainsString('UpdateAgentContractActionsService',$controller);
        $this->assertStringContainsString('{{ $definition->displayName }}',$view);
        $this->assertStringNotContainsString('{!! $definition->displayName !!}',$view);
    }

    public function test_quote_and_guide_handlers_have_domain_validation_before_service_calls(): void
    {
        $quote=file_get_contents(app_path('Domain/Shipping/AI/Actions/QuoteShipmentActionHandler.php'));$guide=file_get_contents(app_path('Domain/Shipping/AI/Actions/CreateShipmentGuideActionHandler.php'));
        $this->assertStringContainsString("package.weight'=>'required|numeric|min:0.01|max:1000",$quote);
        $this->assertStringContainsString("regex:/^[0-9]{5}$/",$quote);
        $this->assertStringContainsString("recipient.postal_code",$guide);
        $validator=app(ActionSchemaValidator::class);$schema=app(ActionRegistry::class)->find('zigo.create_shipment_guide')->inputSchema;
        foreach(['price','provider','service_code']as$field){try{$validator->validate(['quote_reference'=>'q','option_reference'=>'o','sender'=>['name'=>'a','phone'=>'1','street'=>'a','exterior'=>'1','interior'=>'','postal_code'=>'00000'],'recipient'=>['name'=>'b','phone'=>'2','street'=>'b','exterior'=>'2','interior'=>'','postal_code'=>'00000'],'customer_reference'=>'r',$field=>'tampered'],$schema,16384);$this->fail('Authority field must be rejected.');}catch(\DomainException){$this->addToAssertionCount(1);}}
    }
}
