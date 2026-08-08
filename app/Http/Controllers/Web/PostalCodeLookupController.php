<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ZigoPostalCodeService;
use Illuminate\Http\Request;

final class PostalCodeLookupController extends Controller
{
    public function show(string $codigoPostal, ZigoPostalCodeService $postalCodes)
    {
        $result = $postalCodes->lookup($codigoPostal);
        $status = $result['status'];
        unset($result['status']);

        return response()->json($result, $status);
    }

    public function colonias(Request $request, ZigoPostalCodeService $postalCodes)
    {
        $result = $postalCodes->lookup((string) $request->query('cp'));
        $status = $result['status'];
        unset($result['status']);

        if (! ($result['success'] ?? false)) {
            $result['data'] = [];

            return response()->json($result, $status);
        }

        $data = collect($result['colonias'])->map(function (array $colonia) use ($result) {
            return [
                'd_codigo' => $result['codigo_postal'],
                'd_asenta' => $colonia['nombre'],
                'colonia' => $colonia['nombre'],
                'd_tipo_asenta' => $colonia['tipo_asentamiento'],
                'tipo_asentamiento' => $colonia['tipo_asentamiento'],
                'D_mnpio' => $result['municipio'],
                'd_mnpio' => $result['municipio'],
                'municipio' => $result['municipio'],
                'd_estado' => $result['estado'],
                'estado' => $result['estado'],
                'd_ciudad' => $result['ciudad'],
                'ciudad' => $result['ciudad'],
                'zona' => $colonia['zona'],
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
