<?php

namespace App\Domain\Network\Channels\B2C;

use App\Domain\Network\Billing\SubscriptionService;
use App\Domain\Network\Channels\B2C\Exceptions\TenantQuoteUnavailableException;
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\{LocalShippingPackageRule,LocalShippingQuoteSnapshot,LocalShippingService};
use App\Domain\Shipping\Local\{PackageValidator};
use App\Domain\Shipping\Local\Pricing\LocalPricingEngine;
use App\Domain\Shipping\Local\Routing\RouteDistanceProvider;
use App\Services\ZigoPostalCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantB2cQuoteService
{
    public function __construct(private SubscriptionService $subscriptions, private ZigoPostalCodeService $postal, private PackageValidator $packages, private LocalPricingEngine $pricing, private RouteDistanceProvider $routes) {}

    public function quote(Tenant $tenant, array $data): array
    {
        $origin = $this->address($data, 'origin'); $destination = $this->address($data, 'destination');
        $preliminary = blank($data['origin_address'] ?? null) || blank($data['destination_address'] ?? null);
        $services = LocalShippingService::with(['pricingRules','packageRules'])->where('tenant_id',$tenant->id)->where('status','active')->where('published',true)
            ->where(fn($q)=>$q->whereNull('valid_from')->orWhere('valid_from','<=',now()))->where(fn($q)=>$q->whereNull('valid_to')->orWhere('valid_to','>=',now()))
            ->whereHas('originZone',fn($q)=>$this->coveredZone($q,$tenant->id,$data['cp_origen']))
            ->whereHas('destinationZone',fn($q)=>$this->coveredZone($q,$tenant->id,$data['cp_destino']))->orderBy('sort_order')->orderBy('id')->get();
        if ($services->isEmpty()) throw ValidationException::withMessages(['quote'=>'La ruta no tiene cobertura disponible.']);
        $eligible = $services->filter(function($service) use($tenant,$data){
            $rule=$service->packageRules->firstWhere('package_type',$data['tipo_envio']) ?? LocalShippingPackageRule::where('tenant_id',$tenant->id)->whereNull('service_id')->where('package_type',$data['tipo_envio'])->where('active',true)->first();
            if(!$rule)return false; try{$this->packages->validate($rule,['weight'=>$data['peso'],'length'=>$data['length']??null,'width'=>$data['width']??null,'height'=>$data['height']??null]);return true;}catch(ValidationException){return false;}
        });
        if($eligible->isEmpty()) throw ValidationException::withMessages(['package'=>'El paquete excede los límites configurados.']);
        $needsDistance=$eligible->contains(fn($s)=>in_array($s->pricing_strategy,['BASE_PLUS_OVERAGE','DISTANCE_TIERS_OVERAGE'],true));
        $route=$needsDistance?$this->routes->distance($origin['address'],$destination['address']):null;
        $options=[];
        foreach($eligible as $service){
            $requires=in_array($service->pricing_strategy,['BASE_PLUS_OVERAGE','DISTANCE_TIERS_OVERAGE'],true); if($requires&&!$route)continue;
            try{$price=$this->pricing->price($service,$data['tipo_envio'],$route['distance_meters']??null);}catch(\DomainException){continue;}
            $matched = $price->metadata['rule'];
            $matched['_quote_context'] = ['preliminary'=>$preliminary,'requires_distance'=>$requires];
            $snapshot=LocalShippingQuoteSnapshot::create(['tenant_id'=>$tenant->id,'service_id'=>$service->id,'origin'=>$origin,'destination'=>$destination,'package_type'=>$data['tipo_envio'],'weight_kg'=>$data['peso'],'dimensions'=>$data['tipo_envio']==='caja'?['length'=>$data['length'],'width'=>$data['width'],'height'=>$data['height']]:null,'distance_meters'=>$requires?$route['distance_meters']:null,'pricing_strategy'=>$service->pricing_strategy,'matched_tariff'=>$matched,'amount'=>$price->totalAmount,'currency'=>$price->currency,'expires_at'=>now()->addMinutes(30)]);
            $options[]=['snapshot_uuid'=>$snapshot->uuid,'service'=>$service->name,'service_code'=>$service->code,'provider'=>'ZIGO Local','sla'=>$service->sla_text,'price'=>$price->totalAmount,'currency'=>$price->currency,'origin'=>trim(($origin['settlement']??'').' · CP '.($origin['postal_code']??''),' ·'),'destination'=>trim(($destination['settlement']??'').' · CP '.($destination['postal_code']??''),' ·'),'distance_meters'=>$snapshot->distance_meters,'preliminary'=>$preliminary];
        }
        if($options===[]) throw TenantQuoteUnavailableException::noCommercialRates();
        $operation=TenantOperation::create(['tenant_id'=>$tenant->id,'subscription_id'=>$this->subscriptions->currentForTenant($tenant)?->id,'channel'=>'b2c','status'=>'quoted','metadata'=>['quote_snapshot_uuids'=>array_column($options,'snapshot_uuid')]]);
        return ['operation'=>$operation,'options'=>$options];
    }

    public function finalize(Tenant $tenant, LocalShippingQuoteSnapshot $preliminary, array $sender, array $recipient): LocalShippingQuoteSnapshot
    {
        abort_unless((int) $preliminary->tenant_id === (int) $tenant->id, 404);
        $service = LocalShippingService::with(['pricingRules','packageRules'])->where('tenant_id',$tenant->id)->whereKey($preliminary->service_id)->where('status','active')->where('published',true)->firstOrFail();
        $origin = $this->exactAddress($preliminary->origin, $sender);
        $destination = $this->exactAddress($preliminary->destination, $recipient);
        $requires = in_array($service->pricing_strategy,['BASE_PLUS_OVERAGE','DISTANCE_TIERS_OVERAGE'],true);
        $route = $requires ? $this->routes->distance($origin['address'],$destination['address']) : null;
        if ($requires && ! $route) throw ValidationException::withMessages(['route'=>'No fue posible calcular la ruta con las direcciones completas.']);
        $price = $this->pricing->price($service,$preliminary->package_type,$route['distance_meters']??null);
        $matched = $price->metadata['rule'];
        $matched['_quote_context'] = ['preliminary'=>false,'requires_distance'=>$requires,'source_snapshot_uuid'=>$preliminary->uuid];
        return LocalShippingQuoteSnapshot::create([
            'tenant_id'=>$tenant->id,'service_id'=>$service->id,'origin'=>$origin,'destination'=>$destination,
            'package_type'=>$preliminary->package_type,'weight_kg'=>$preliminary->weight_kg,'dimensions'=>$preliminary->dimensions,
            'distance_meters'=>$requires?$route['distance_meters']:null,'pricing_strategy'=>$service->pricing_strategy,
            'matched_tariff'=>$matched,'amount'=>$price->totalAmount,'currency'=>$price->currency,'expires_at'=>now()->addMinutes(30),
        ]);
    }

    private function coveredZone($query,int $tenantId,string $cp): void {$query->where('tenant_id',$tenantId)->where('status','active')->whereHas('postalCodes',fn($q)=>$q->where('tenant_id',$tenantId)->where('postal_code',$cp)->where('active',true)->where(fn($v)=>$v->whereNull('valid_from')->orWhere('valid_from','<=',now()))->where(fn($v)=>$v->whereNull('valid_to')->orWhere('valid_to','>=',now())));}
    private function address(array $data,string $side): array {$cp=$data[$side==='origin'?'cp_origen':'cp_destino'];$lookup=$this->postal->lookup($cp);if(!($lookup['success']??false))throw ValidationException::withMessages([$side=>"No encontramos el código postal {$cp} en SEPOMEX."]);$settlement=$data[$side.'_settlement'];$valid=collect($lookup['colonias'])->contains(fn($c)=>hash_equals((string)$c['nombre'],$settlement));if(!$valid)throw ValidationException::withMessages([$side.'_settlement'=>'Selecciona una colonia válida.']);$street=trim((string)($data[$side.'_address']??''));$parts=array_filter([$street,$settlement,$cp,$lookup['municipio'],$lookup['estado'],'México'],fn($v)=>filled($v));return ['address'=>implode(', ',$parts),'street'=>$street?:null,'postal_code'=>$cp,'settlement'=>$settlement,'municipality'=>$lookup['municipio'],'state'=>$lookup['estado'],'country'=>'México'];}

    private function exactAddress(array $base,array $person): array
    {
        $street=trim((string)$person['street']);$exterior=trim((string)$person['exterior']);$interior=trim((string)($person['interior']??''));
        $line=trim($street.' '.$exterior).($interior!==''?', Int. '.$interior:'');
        return array_merge($base,['address'=>implode(', ',array_filter([$line,$base['settlement']??null,$base['postal_code']??null,$base['municipality']??null,$base['state']??null,'México'],fn($v)=>filled($v))),'street'=>$street,'exterior'=>$exterior,'interior'=>$interior?:null,'country'=>'México']);
    }
}
