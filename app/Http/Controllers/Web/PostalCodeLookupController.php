<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ZigoPostalCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostalCodeLookupController extends Controller
{
    public function show(string $codigoPostal, ZigoPostalCodeService $postalCodeService): JsonResponse
    {
        $result = $postalCodeService->lookup($codigoPostal);

        $status = $result['status'];
        unset($result['status']);

        return response()->json($result, $status);
    }

    public function colonias(Request $request, ZigoPostalCodeService $postalCodeService): JsonResponse
    {
        $codigoPostal = (string) $request->query('cp', '');

        $result = $postalCodeService->lookup($codigoPostal);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => [],
            ], $result['status']);
        }

        $data = collect($result['colonias'])->map(function ($item) use ($result) {
            return [
                'd_codigo' => $result['codigo_postal'],
                'd_asenta' => $item['nombre'],
                'd_tipo_asenta' => $item['tipo_asentamiento'],
                'D_mnpio' => $result['municipio'],
                'd_estado' => $result['estado'],
                'd_ciudad' => $result['ciudad'],
                'zona' => $item['zona'],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}