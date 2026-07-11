<?php

namespace App\Http\Controllers\API\Hub;

use App\Http\Controllers\Controller;
use App\Services\ZigoPostalCodeService;
use Illuminate\Http\JsonResponse;

class PostalCodeController extends Controller
{
    public function show(string $codigoPostal, ZigoPostalCodeService $postalCodeService): JsonResponse
    {
        $result = $postalCodeService->lookup($codigoPostal);

        $status = $result['status'];
        unset($result['status']);

        return response()->json($result, $status);
    }
}