<?php

namespace App\Domain\Shipping\AI\Actions;

use App\Domain\AI\Actions\ActionRegistry;
use App\Domain\AI\Actions\Data\ActionDefinition;
use App\Domain\AI\Actions\Enums\{ActionConfirmationPolicy, ActionEffect};

final class ZigoLogisticsActionRegistrar
{
    public function __construct(private ActionRegistry $registry, private QuoteShipmentActionHandler $quote, private TrackShipmentActionHandler $track, private CreateShipmentGuideActionHandler $guide, private CreateShipmentGuideConfirmationPresenter $guideConfirmation) {}

    public function register(): void
    {
        $this->registry->register(new ActionDefinition('zigo.quote_shipment','ZIGO — Cotizar envío','Obtiene opciones comerciales vigentes de ZIGO.',$this->quoteInput(),$this->quoteOutput(),ActionEffect::Read,ActionConfirmationPolicy::None,$this->quote,['SHIPPING']));
        $this->registry->register(new ActionDefinition('zigo.track_shipment','ZIGO — Rastrear envío','Consulta el estado del envío del tenant.',$this->trackInput(),$this->trackOutput(),ActionEffect::Read,ActionConfirmationPolicy::None,$this->track,['TRACKING']));
        $this->registry->register(new ActionDefinition('zigo.create_shipment_guide','ZIGO — Crear guía','Crea una guía desde una cotización server-side confirmada.',$this->guideInput(),$this->guideOutput(),ActionEffect::Write,ActionConfirmationPolicy::Required,$this->guide,['SHIPPING'],$this->guideConfirmation));
    }

    private function quoteInput(): array { return ['type'=>'object','additionalProperties'=>false,'required'=>['origin_postal_code','destination_postal_code','origin_settlement','destination_settlement','package'],'properties'=>['origin_postal_code'=>['type'=>'string'],'destination_postal_code'=>['type'=>'string'],'origin_settlement'=>['type'=>'string'],'destination_settlement'=>['type'=>'string'],'package'=>$this->packageSchema()]]; }
    private function quoteOutput(): array { return ['type'=>'object','additionalProperties'=>false,'required'=>['quote_reference','options'],'properties'=>['quote_reference'=>['type'=>'string'],'options'=>['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'required'=>['option_reference','service_name','amount','currency','estimated_delivery'],'properties'=>['option_reference'=>['type'=>'string'],'service_name'=>['type'=>'string'],'amount'=>['type'=>'number'],'currency'=>['type'=>'string'],'estimated_delivery'=>['type'=>'string']]]]]]; }
    private function trackInput(): array { return ['type'=>'object','additionalProperties'=>false,'required'=>['shipment_reference'],'properties'=>['shipment_reference'=>['type'=>'string']]]; }
    private function trackOutput(): array { $event=['type'=>'object','additionalProperties'=>false,'required'=>['status_code','status_label','occurred_at'],'properties'=>['status_code'=>['type'=>'string'],'status_label'=>['type'=>'string'],'occurred_at'=>['type'=>'string']]]; return ['type'=>'object','additionalProperties'=>false,'required'=>['shipment_reference','tracking_reference','status_code','status_label','delivered','events'],'properties'=>['shipment_reference'=>['type'=>'string'],'tracking_reference'=>['type'=>'string'],'status_code'=>['type'=>'string'],'status_label'=>['type'=>'string'],'delivered'=>['type'=>'boolean'],'events'=>['type'=>'array','items'=>$event]]]; }
    private function guideInput(): array { $person=['type'=>'object','additionalProperties'=>false,'required'=>['name','phone','street','exterior','interior','postal_code'],'properties'=>['name'=>['type'=>'string'],'phone'=>['type'=>'string'],'street'=>['type'=>'string'],'exterior'=>['type'=>'string'],'interior'=>['type'=>'string'],'postal_code'=>['type'=>'string']]]; return ['type'=>'object','additionalProperties'=>false,'required'=>['quote_reference','option_reference','sender','recipient','customer_reference'],'properties'=>['quote_reference'=>['type'=>'string'],'option_reference'=>['type'=>'string'],'sender'=>$person,'recipient'=>$person,'customer_reference'=>['type'=>'string']]]; }
    private function guideOutput(): array { return ['type'=>'object','additionalProperties'=>false,'required'=>['shipment_reference','guide_reference','tracking_reference','status','document_available'],'properties'=>['shipment_reference'=>['type'=>'string'],'guide_reference'=>['type'=>'string'],'tracking_reference'=>['type'=>'string'],'status'=>['type'=>'string'],'document_available'=>['type'=>'boolean']]]; }
    private function packageSchema(): array { return ['type'=>'object','additionalProperties'=>false,'required'=>['type','weight','length','width','height'],'properties'=>['type'=>['type'=>'string','enum'=>['sobre','caja']],'weight'=>['type'=>'number'],'length'=>['type'=>'number'],'width'=>['type'=>'number'],'height'=>['type'=>'number']]]; }
}
