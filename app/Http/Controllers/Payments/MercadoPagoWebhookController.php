<?php
namespace App\Http\Controllers\Payments;
use App\Domain\Payments\TenantPaymentService; use App\Http\Controllers\Controller; use Illuminate\Http\Request;
final class MercadoPagoWebhookController extends Controller{public function __invoke(Request $r,TenantPaymentService $s){$s->webhook($r);return response()->json(['received'=>true]);}}
