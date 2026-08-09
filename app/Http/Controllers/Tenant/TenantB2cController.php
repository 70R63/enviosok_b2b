<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\Exceptions\TenantQuoteUnavailableException;
use App\Domain\Network\Channels\B2C\TenantB2cQuoteService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Channels\B2C\Models\TenantCustomerProfile;

final class TenantB2cController extends Controller
{
    public function create(TenantContext $context)
    {
        return view('tenant.b2c.quote', ['tenant' => $context->tenant()->load('branding'), 'result' => null, 'customerPortal' => false]);
    }

    public function store(Request $request, TenantContext $context, TenantB2cQuoteService $quotes)
    {
        $data = $request->validate([
            'cp_origen' => ['required', 'regex:/^\d{5}$/'], 'cp_destino' => ['required', 'regex:/^\d{5}$/'],
            'tipo_envio' => ['required', 'in:sobre,caja'], 'peso' => ['required', 'numeric', 'min:0.1', 'max:70'],
            'length' => ['required_if:tipo_envio,caja', 'nullable', 'numeric', 'min:1', 'max:300'],
            'width' => ['required_if:tipo_envio,caja', 'nullable', 'numeric', 'min:1', 'max:300'],
            'height' => ['required_if:tipo_envio,caja', 'nullable', 'numeric', 'min:1', 'max:300'],
        ]);
        $tenant = $context->tenant()->load('branding');
        try {
            $result = $quotes->quote($tenant, $data);
        } catch (TenantQuoteUnavailableException) {
            return back()->withInput()->withErrors([
                'quote' => 'No fue posible obtener tarifas disponibles para este envío en este momento. Verifica los datos o intenta nuevamente.',
            ]);
        }

        $request->session()->put('tenant_customer.quote_result', [
            'tenant_id' => $tenant->id, 'operation_uuid' => $result['operation']->uuid,
            'options' => collect($result['options'])->values()->all(),
        ]);
        return view('tenant.b2c.quote', ['tenant' => $tenant, 'result' => $result, 'customerPortal' => $request->is('app/*')]);
    }

    public function select(Request $request, TenantContext $context)
    {
        $data = $request->validate(['operation_uuid' => ['required', 'uuid'], 'option' => ['required', 'integer', 'min:0']]);
        $quote = $request->session()->get('tenant_customer.quote_result');
        abort_unless(is_array($quote) && (int) ($quote['tenant_id'] ?? 0) === $context->id()
            && hash_equals((string) ($quote['operation_uuid'] ?? ''), $data['operation_uuid']), 404);
        $option = $quote['options'][$data['option']] ?? null;
        abort_unless(is_array($option), 404);
        $operation = TenantOperation::where('tenant_id', $context->id())->where('uuid', $data['operation_uuid'])->where('status', 'quoted')->firstOrFail();
        $metadata = $operation->metadata ?? [];
        $metadata['selected_quote'] = ['service' => $option['service'] ?? null, 'delivery' => $option['delivery'] ?? null, 'price' => $option['price'] ?? null, 'currency' => 'MXN'];
        $metadata['final_price'] = $option['price'] ?? $metadata['final_price'] ?? null;
        $operation->update(['provider' => $option['provider'] ?? null, 'service_code' => $option['service_code'] ?? null, 'metadata' => $metadata]);
        if (auth()->check()) {
            $profile = TenantCustomerProfile::where('tenant_id', $context->id())->where('user_id', auth()->id())->where('status', 'active')->first();
            if ($profile) {
                $operation->update(['customer_profile_id' => $profile->id, 'created_by_user_id' => auth()->id()]);
                return redirect('/app')->with('success', 'Servicio seleccionado. Tu cotización quedó guardada para continuar.');
            }
        }
        $request->session()->put('tenant_customer.pending_quote', ['tenant_id' => $context->id(), 'operation_uuid' => $operation->uuid]);
        return redirect('/login')->with('status', 'Inicia sesión o crea tu cuenta para continuar con este servicio.');
    }

    public function tracking(TenantContext $context)
    {
        return view('tenant.b2c.tracking', ['tenant' => $context->tenant()->load('branding')]);
    }

    public function track(string $tracking, TenantContext $context)
    {
        $shipment = LocalShipment::with('events')->where('tenant_id', $context->tenant()->id)->where('tracking_number', $tracking)->firstOrFail();

        return view('tenant.b2c.tracking-result', [
            'tenant' => $context->tenant()->load('branding'),
            'trackingResult' => [
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status,
                'events' => $shipment->events->map(fn ($event) => [
                    'status' => $event->status,
                    'occurred_at' => $event->occurred_at,
                ]),
            ],
        ]);
    }
}
