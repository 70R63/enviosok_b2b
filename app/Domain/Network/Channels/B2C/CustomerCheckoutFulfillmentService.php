<?php

namespace App\Domain\Network\Channels\B2C;

use App\Domain\Network\Channels\B2C\Models\TenantCustomerCheckout;
use App\Domain\Shipping\Local\{LocalShipmentService,LocalTrackingService};
use App\Domain\Shipping\LastMile\DriverDispatchService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Support\Facades\DB;

final class CustomerCheckoutFulfillmentService
{
    public function __construct(private TenantOperationService $operations, private LocalShipmentService $shipments, private LocalTrackingService $tracking, private DriverDispatchService $dispatch) {}

    /** Entry point reserved for a provider-verified APPROVED payment event. It has no web/customer route in ZN-UX-04. */
    public function approve(TenantCustomerCheckout $checkout, string $provider, string $reference): LocalShipment
    {
        abort_unless($provider !== '' && $reference !== '', 422);
        return DB::transaction(function () use ($checkout, $provider, $reference): LocalShipment {
            $locked = TenantCustomerCheckout::with(['tenant', 'operation'])->whereKey($checkout->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['PENDING_PAYMENT', 'PAID'], true), 409);
            if ($locked->status !== 'PAID') {
                abort_if($locked->expires_at?->isPast(), 409);
                $locked->update(['status' => 'PAID', 'payment_status' => 'APPROVED', 'payment_provider' => $provider, 'payment_reference' => $reference, 'paid_at' => now()]);
            } else {
                abort_unless(hash_equals((string) $locked->payment_provider, $provider) && hash_equals((string) $locked->payment_reference, $reference), 409);
            }
            $operation = $this->operations->confirm($locked->tenant, $locked->operation->fresh());
            $existing = LocalShipment::where('tenant_operation_id', $operation->id)->first();
            if ($existing) return $existing;
            $data = $locked->shipping_data_snapshot;
            $data['pricing'] = ['shipping_amount' => (float) $locked->shipping_amount, 'evidence_amount' => (float) $locked->evidence_amount, 'final_price' => (float) $locked->total_amount, 'currency' => $locked->currency];
            $data['delivery_requirement_snapshot'] = $locked->proof_option_snapshot;
            $shipment = $this->shipments->create($locked->tenant, $operation, $data, $locked->customerProfile->user_id);
            if (($data['pickup_requested'] ?? false) && $shipment->status === 'CREATED') {
                $this->tracking->transition($shipment, 'READY_FOR_PICKUP', $locked->customerProfile->user_id, 'Recolección solicitada después de pago aprobado.');
                $this->dispatch->autoAssign($locked->tenant, $shipment->fresh(), $locked->customerProfile->user_id);
            }
            return $shipment->fresh();
        });
    }
}
