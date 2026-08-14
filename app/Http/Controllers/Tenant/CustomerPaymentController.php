<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\Models\TenantCustomerCheckout;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Payments\Models\TenantPaymentConnection;
use App\Domain\Payments\TenantPaymentService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class CustomerPaymentController extends Controller
{
    public function create(Request $request, string $checkout, TenantContext $context, TenantPaymentService $payments)
    {
        $item = $this->checkout($request, $context, $checkout);
        $connection = TenantPaymentConnection::where('tenant_id', $context->id())->where('provider', 'MERCADO_PAGO')->where('status', 'CONNECTED')->firstOrFail();
        $attempt = $payments->createAttempt($item, $connection);
        if (! $attempt->init_point) $attempt = $payments->initialize($attempt);
        return redirect()->away($attempt->getRawOriginal('init_point'));
    }

    public function returned(Request $request, string $checkout, string $result, TenantContext $context)
    {
        abort_unless(in_array($result, ['success','pending','failure'], true), 404);
        $item = $this->checkout($request, $context, $checkout)->fresh();
        Log::info('Mercado Pago customer callback', ['tenant_id'=>$context->id(), 'checkout_uuid'=>$item->uuid, 'result'=>$result, 'status'=>$item->status, 'payment_status'=>$item->payment_status]);
        $profile = $request->attributes->get('customer_profile');
        $shipment = $item->status === 'PAID' ? LocalShipment::query()->select('local_shipments.*')->join('network_tenant_operations as customer_operations', 'customer_operations.id', '=', 'local_shipments.tenant_operation_id')->where('local_shipments.tenant_id', $context->id())->where('customer_operations.customer_profile_id', $profile->id)->where('customer_operations.id', $item->tenant_operation_id)->first() : null;
        return view('tenant.customer.journey.payment-return', ['tenant'=>$context->tenant()->load('branding'), 'checkout'=>$item, 'shipment'=>$shipment, 'result'=>$result]);
    }

    private function checkout(Request $request, TenantContext $context, string $uuid): TenantCustomerCheckout
    {
        $profile = $request->attributes->get('customer_profile');
        return TenantCustomerCheckout::where('tenant_id', $context->id())->where('customer_profile_id', $profile->id)->where('uuid', $uuid)->firstOrFail();
    }
}
