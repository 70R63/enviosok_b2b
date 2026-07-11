<?php

namespace App\Services;

use App\Models\ZigoPostalCode;
use Illuminate\Support\Collection;

class ZigoPostalCodeService
{
    public function lookup(string $codigoPostal): array
    {
        $codigoPostal = trim($codigoPostal);

        if (!preg_match('/^[0-9]{5}$/', $codigoPostal)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'El código postal debe contener exactamente 5 dígitos.',
                'codigo_postal' => $codigoPostal,
            ];
        }

        $colonias = ZigoPostalCode::query()
            ->where('codigo_postal', $codigoPostal)
            ->where('activo', true)
            ->orderBy('asentamiento')
            ->get();

        if ($colonias->isEmpty()) {
            return [
                'success' => false,
                'status' => 404,
                'message' => 'Código postal no encontrado.',
                'codigo_postal' => $codigoPostal,
            ];
        }

        $primerRegistro = $colonias->first();

        return [
            'success' => true,
            'status' => 200,
            'service' => 'zigo.cp.lookup',
            'codigo_postal' => $codigoPostal,
            'estado' => $primerRegistro->estado,
            'municipio' => $primerRegistro->municipio,
            'ciudad' => $primerRegistro->ciudad,
            'colonias' => $this->mapColonias($colonias),
            'cobertura' => [
                'disponible' => $colonias->contains('cobertura_estafeta', true),
                'carrier' => 'Estafeta',
            ],
            'meta' => [
                'total_colonias' => $colonias->count(),
            ],
        ];
    }

    private function mapColonias(Collection $colonias): array
    {
        return $colonias->map(function ($item) {
            return [
                'nombre' => $item->asentamiento,
                'tipo_asentamiento' => $item->tipo_asentamiento,
                'zona' => $item->zona,
            ];
        })->values()->toArray();
    }
}