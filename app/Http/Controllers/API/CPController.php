<?php

namespace App\Http\Controllers\API;

use App\Services\ZigoPostalCodeService;
use Illuminate\Http\Request;

final class CPController extends ApiController
{
    public function colonias(Request $request, ZigoPostalCodeService $postalCodes)
    {
        $result = $postalCodes->lookup((string) $request->query('cp'));
        if (! ($result['success'] ?? false)) {
            return $this->sendError('Postal code lookup failed', $result['message'], (string) $result['status']);
        }

        $data = collect($result['colonias'])->map(fn (array $colonia) => [
            'd_codigo' => $result['codigo_postal'],
            'd_asenta' => $colonia['nombre'],
            'd_tipo_asenta' => $colonia['tipo_asentamiento'],
            'd_mnpio' => $result['municipio'],
            'd_estado' => $result['estado'],
            'd_ciudad' => $result['ciudad'],
            'd_zona' => $colonia['zona'],
        ])->values();

        return $this->successResponse($data, 'ok');
    }
}
