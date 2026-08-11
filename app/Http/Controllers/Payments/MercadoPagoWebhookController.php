<?php
namespace App\Http\Controllers\Payments;
use App\Domain\Payments\TenantPaymentService; use App\Domain\Network\Commerce\PlatformPaymentService; use App\Http\Controllers\Controller; use Illuminate\Http\Request;
final class MercadoPagoWebhookController extends Controller{public function __invoke(Request $r,TenantPaymentService $seller,PlatformPaymentService $platform){($r->query->has('saas_attempt')||$r->query->has('onboarding_attempt'))?$platform->webhook($r):$seller->webhook($r);return response()->json(['received'=>true]);}}
