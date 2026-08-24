<?php
namespace App\Domain\Shipping\AI\Actions;
use App\Domain\AI\Actions\Contracts\ActionConfirmationPresenter;
use App\Domain\AI\Actions\Models\ActionRun;
use App\Domain\Shipping\Local\Models\LocalShippingQuoteSnapshot;
final class CreateShipmentGuideConfirmationPresenter implements ActionConfirmationPresenter
{
 public function present(ActionRun$action):array
 {
  $input=$action->input;$snapshot=LocalShippingQuoteSnapshot::query()->with('service')->where('tenant_id',$action->tenant_id)->where('uuid',$input['option_reference']??'')->firstOrFail();
  return['title'=>'Confirmar operación','fields'=>[
   ['label'=>'Servicio','value'=>(string)($snapshot->service?->name??'Servicio seleccionado')],
   ['label'=>'Origen','value'=>(string)($snapshot->origin['postal_code']??'No disponible')],
   ['label'=>'Destino','value'=>(string)($snapshot->destination['postal_code']??'No disponible')],
   ['label'=>'Total final','value'=>number_format((float)$snapshot->amount,2,'.',',')],
   ['label'=>'Moneda','value'=>(string)$snapshot->currency],
  ]];
 }
}
