<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Network\Commerce\PlatformPaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MercadoPagoPlatformWebhookController extends Controller
{
    public function __invoke(Request $request, PlatformPaymentService $platform): JsonResponse
    {
        $platform->webhook($request);

        return response()->json(['received' => true]);
    }
}
