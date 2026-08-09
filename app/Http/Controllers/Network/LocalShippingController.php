<?php

namespace App\Http\Controllers\Network;

use App\Domain\Shipping\Local\LocalCoverageService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Domain\Shipping\Local\Models\LocalShippingZone;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class LocalShippingController extends Controller
{
    public function index()
    {
        return view('network.local-shipping.index', [
            'zones' => LocalShippingZone::with('postalCodes')->orderBy('name')->get(),
            'services' => LocalShippingService::with(['originZone', 'destinationZone'])->orderBy('name')->get(),
            'shipments' => LocalShipment::with(['tenant', 'activeDriverAssignment.driverProfile.user'])->latest()->paginate(25),
        ]);
    }

    public function storeZone(Request $request)
    {
        LocalShippingZone::create($request->validate(['code' => ['required', 'alpha_dash', 'max:40', 'unique:local_shipping_zones,code'], 'name' => ['required', 'max:100'], 'status' => ['required', 'in:active,inactive']]));

        return back()->with('success', 'Zona creada.');
    }

    public function storePostalCode(Request $request, LocalShippingZone $zone, LocalCoverageService $coverage)
    {
        $data = $request->validate(['postal_code' => ['required', 'regex:/^\d{5}$/']]);
        $coverage->assign($zone, $data['postal_code']);

        return back()->with('success', 'Código postal asignado.');
    }

    public function storeService(Request $request)
    {
        LocalShippingService::create($request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', 'unique:local_shipping_services,code'], 'name' => ['required', 'max:100'],
            'origin_zone_id' => ['required', 'exists:local_shipping_zones,id'], 'destination_zone_id' => ['required', 'exists:local_shipping_zones,id'],
            'service_level' => ['required', 'in:same_day,next_day,scheduled'], 'base_cost' => ['required', 'numeric', 'min:0'], 'base_price' => ['required', 'numeric', 'min:0'], 'currency' => ['required', 'size:3'],
            'max_weight' => ['nullable', 'numeric', 'gt:0'], 'max_length' => ['nullable', 'numeric', 'gt:0'], 'max_width' => ['nullable', 'numeric', 'gt:0'], 'max_height' => ['nullable', 'numeric', 'gt:0'],
            'estimated_min_hours' => ['nullable', 'integer', 'min:1'], 'estimated_max_hours' => ['nullable', 'integer', 'gte:estimated_min_hours'], 'status' => ['required', 'in:active,inactive'],
        ]));

        return back()->with('success', 'Servicio creado.');
    }

    public function toggleZone(LocalShippingZone $zone)
    {
        $zone->update(['status' => $zone->status === 'active' ? 'inactive' : 'active']);

        return back();
    }

    public function toggleService(LocalShippingService $service)
    {
        $service->update(['status' => $service->status === 'active' ? 'inactive' : 'active']);

        return back();
    }
}
