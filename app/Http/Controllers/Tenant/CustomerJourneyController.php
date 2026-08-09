<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\CustomerCheckoutService;
use App\Domain\Network\Channels\B2C\Models\{TenantCustomerCheckout, TenantOperation};
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;
use App\Domain\Shipping\Local\{LocalTrackingService};
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Http\Controllers\Controller;
use App\Services\ZigoPostalCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerJourneyController extends Controller
{
    public function shipping(Request $request, TenantContext $context)
    {
        $operation = $this->activeOperation($request, $context);
        return view('tenant.customer.journey.shipping', ['tenant' => $context->tenant()->load('branding'), 'operation' => $operation, 'package' => $operation->metadata['quoted_package'] ?? []]);
    }

    public function storeShipping(Request $request, TenantContext $context, ZigoPostalCodeService $postal)
    {
        $operation = $this->activeOperation($request, $context);
        $data = $request->validate([
            'sender.name' => ['required','string','max:120'], 'sender.phone' => ['required','string','max:30'], 'sender.address' => ['required','string','max:300'],
            'sender.postal_code' => ['required','regex:/^\d{5}$/'], 'sender.neighborhood' => ['required','string','max:160'], 'sender.references' => ['nullable','string','max:300'],
            'recipient.name' => ['required','string','max:120'], 'recipient.phone' => ['required','string','max:30'], 'recipient.address' => ['required','string','max:300'],
            'recipient.postal_code' => ['required','regex:/^\d{5}$/'], 'recipient.neighborhood' => ['required','string','max:160'], 'recipient.references' => ['nullable','string','max:300'],
            'reference' => ['nullable','string','max:100'],
        ]);
        $metadata = $operation->metadata ?? [];
        if ($data['sender']['postal_code'] !== ($metadata['origin_postal_code'] ?? null) || $data['recipient']['postal_code'] !== ($metadata['destination_postal_code'] ?? null)) {
            throw ValidationException::withMessages(['postal_code' => 'Los códigos postales deben coincidir con la ruta cotizada. Vuelve a cotizar para cambiar la ruta.']);
        }
        foreach (['sender','recipient'] as $side) {
            $lookup = $postal->lookup($data[$side]['postal_code']);
            if (! ($lookup['success'] ?? false)) throw ValidationException::withMessages([$side.'.postal_code' => 'No encontramos este código postal en el catálogo.']);
            $allowed = collect($lookup['colonias'])->pluck('nombre')->contains(fn ($name) => hash_equals((string) $name, $data[$side]['neighborhood']));
            if (! $allowed) throw ValidationException::withMessages([$side.'.neighborhood' => 'Selecciona una colonia válida para este código postal.']);
            $data[$side]['state'] = $lookup['estado']; $data[$side]['municipality'] = $lookup['municipio']; $data[$side]['city'] = $lookup['ciudad'];
        }
        $data['package'] = $metadata['quoted_package'];
        DB::transaction(function () use ($operation, $data): void {
            $locked = TenantOperation::whereKey($operation->id)->where('status', 'quoted')->lockForUpdate()->firstOrFail();
            abort_if($locked->customerCheckout()->exists(), 409);
            $metadata = $locked->metadata ?? []; $metadata['shipping_data'] = $data; $locked->update(['metadata' => $metadata]);
        });
        return redirect('/app/envio/evidencia');
    }

    public function evidence(Request $request, TenantContext $context)
    {
        $operation = $this->activeOperation($request, $context);
        abort_unless(isset($operation->metadata['shipping_data']), 409);
        $options = TenantDeliveryProofOption::where('tenant_id', $context->id())->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        return view('tenant.customer.journey.evidence', ['tenant' => $context->tenant()->load('branding'), 'operation' => $operation, 'options' => $options]);
    }

    public function storeEvidence(Request $request, TenantContext $context, CustomerCheckoutService $checkouts)
    {
        $operation = $this->activeOperation($request, $context);
        $data = $request->validate(['proof_option_uuid' => ['required','uuid']]);
        $proof = TenantDeliveryProofOption::where('tenant_id', $context->id())->where('uuid', $data['proof_option_uuid'])->where('is_active', true)->firstOrFail();
        $checkout = $checkouts->create($context->tenant(), $request->attributes->get('customer_profile'), $operation, $proof);
        return redirect('/app/checkout/'.$checkout->uuid.'/resumen');
    }

    public function summary(Request $request, string $checkout, TenantContext $context)
    {
        $item = $this->checkout($request, $context, $checkout);
        return view('tenant.customer.journey.summary', ['tenant' => $context->tenant()->load('branding'), 'checkout' => $item]);
    }

    public function continuePayment(Request $request, string $checkout, TenantContext $context, CustomerCheckoutService $checkouts)
    {
        $item = $checkouts->pending($this->checkout($request, $context, $checkout));
        return redirect('/app/checkout/'.$item->uuid.'/pago');
    }

    public function payment(Request $request, string $checkout, TenantContext $context)
    {
        $item = $this->checkout($request, $context, $checkout);
        if ($item->expires_at?->isPast() && $item->status !== 'PAID') { $item->update(['status' => 'EXPIRED']); $item->refresh(); }
        return view('tenant.customer.journey.payment', ['tenant' => $context->tenant()->load('branding'), 'checkout' => $item]);
    }

    public function pickup(Request $request, string $shipment, TenantContext $context, LocalTrackingService $tracking, \App\Domain\Shipping\LastMile\DriverDispatchService $dispatch)
    {
        $profile = $request->attributes->get('customer_profile');
        $item = LocalShipment::query()->select('local_shipments.*')->join('network_tenant_operations as owned_ops','owned_ops.id','=','local_shipments.tenant_operation_id')
            ->where('local_shipments.tenant_id',$context->id())->where('owned_ops.customer_profile_id',$profile->id)->where('local_shipments.uuid',$shipment)->firstOrFail();
        if (! in_array($item->status, ['CREATED','READY_FOR_PICKUP'], true)) throw ValidationException::withMessages(['pickup' => 'Este envío ya no permite solicitar recolección.']);
        try { $tracking->transition($item, 'READY_FOR_PICKUP', auth()->id(), 'Recolección solicitada.'); $dispatch->autoAssign($context->tenant(), $item->fresh(), auth()->id()); }
        catch (\DomainException $e) { throw ValidationException::withMessages(['pickup' => $e->getMessage()]); }
        return back()->with('success', 'Recolección solicitada.');
    }

    private function activeOperation(Request $request, TenantContext $context): TenantOperation
    {
        $profile = $request->attributes->get('customer_profile');
        $uuid = (string) $request->session()->get('tenant_customer.active_operation', '');
        return TenantOperation::where('tenant_id', $context->id())->where('customer_profile_id', $profile->id)->where('uuid', $uuid)->where('status', 'quoted')->firstOrFail();
    }

    private function checkout(Request $request, TenantContext $context, string $uuid): TenantCustomerCheckout
    {
        $profile = $request->attributes->get('customer_profile');
        return TenantCustomerCheckout::with('operation')->where('tenant_id', $context->id())->where('customer_profile_id', $profile->id)->where('uuid', $uuid)->firstOrFail();
    }
}
