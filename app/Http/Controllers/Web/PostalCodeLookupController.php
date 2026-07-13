<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostalCodeLookupController extends Controller
{
    public function show(string $codigoPostal)
    {
        $codigoPostal = $this->cleanPostalCode($codigoPostal);

        if (!$this->isValidPostalCode($codigoPostal)) {
            return response()->json([
                'success' => false,
                'message' => 'Código postal inválido.',
            ], 422);
        }

        $rows = $this->findPostalCodeRows($codigoPostal);

        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Código postal no encontrado.',
                'codigo_postal' => $codigoPostal,
            ], 404);
        }

        $first = $rows->first();

        return response()->json([
            'success' => true,
            'service' => 'zigo.cp.lookup',
            'codigo_postal' => $codigoPostal,
            'estado' => $first['estado'] ?? null,
            'municipio' => $first['municipio'] ?? null,
            'ciudad' => $first['ciudad'] ?? ($first['municipio'] ?? null),
            'colonias' => $rows->map(function ($row) {
                return [
                    'nombre' => $row['colonia'] ?? null,
                    'tipo_asentamiento' => $row['tipo_asentamiento'] ?? null,
                    'zona' => $row['zona'] ?? null,
                ];
            })->values(),
            'cobertura' => [
                'disponible' => true,
                'carrier' => 'Estafeta',
            ],
            'meta' => [
                'total_colonias' => $rows->count(),
            ],
        ]);
    }

    public function colonias(Request $request)
    {
        $codigoPostal = $this->cleanPostalCode($request->query('cp'));

        if (!$this->isValidPostalCode($codigoPostal)) {
            return response()->json([
                'success' => false,
                'message' => 'Código postal inválido.',
                'data' => [],
            ], 422);
        }

        $rows = $this->findPostalCodeRows($codigoPostal);

        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Código postal no encontrado.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $rows->map(function ($row) use ($codigoPostal) {
                return [
                    'd_codigo' => $codigoPostal,
                    'd_asenta' => $row['colonia'] ?? null,
                    'colonia' => $row['colonia'] ?? null,
                    'd_tipo_asenta' => $row['tipo_asentamiento'] ?? null,
                    'tipo_asentamiento' => $row['tipo_asentamiento'] ?? null,
                    'D_mnpio' => $row['municipio'] ?? null,
                    'd_mnpio' => $row['municipio'] ?? null,
                    'municipio' => $row['municipio'] ?? null,
                    'd_estado' => $row['estado'] ?? null,
                    'estado' => $row['estado'] ?? null,
                    'd_ciudad' => $row['ciudad'] ?? ($row['municipio'] ?? null),
                    'ciudad' => $row['ciudad'] ?? ($row['municipio'] ?? null),
                    'zona' => $row['zona'] ?? null,
                ];
            })->values(),
        ]);
    }

    private function cleanPostalCode(?string $codigoPostal): string
    {
        return preg_replace('/\D/', '', (string) $codigoPostal);
    }

    private function isValidPostalCode(string $codigoPostal): bool
    {
        return preg_match('/^\d{5}$/', $codigoPostal) === 1;
    }

    private function findPostalCodeRows(string $codigoPostal)
    {
        if (Schema::hasTable('zigo_postal_codes')) {
            $rows = $this->findInZigoPostalCodes($codigoPostal);

            if ($rows->isNotEmpty()) {
                return $rows;
            }
        }

        if (Schema::hasTable('sepomex')) {
            $rows = $this->findInSepomex($codigoPostal);

            if ($rows->isNotEmpty()) {
                return $rows;
            }
        }

        return collect();
    }

    private function findInZigoPostalCodes(string $codigoPostal)
    {
        $table = 'zigo_postal_codes';
        $columns = Schema::getColumnListing($table);

        $cpColumn = $this->firstExistingColumn($columns, [
            'codigo_postal',
            'd_codigo',
            'cp',
            'postal_code',
        ]);

        if (!$cpColumn) {
            return collect();
        }

        return DB::table($table)
            ->where($cpColumn, $codigoPostal)
            ->get()
            ->map(function ($row) {
                return $this->normalizeRow((array) $row);
            })
            ->filter(fn ($row) => !empty($row['colonia']))
            ->values();
    }

    private function findInSepomex(string $codigoPostal)
    {
        $table = 'sepomex';
        $columns = Schema::getColumnListing($table);

        $cpColumn = $this->firstExistingColumn($columns, [
            'd_codigo',
            'codigo_postal',
            'cp',
            'postal_code',
        ]);

        if (!$cpColumn) {
            return collect();
        }

        return DB::table($table)
            ->where($cpColumn, $codigoPostal)
            ->get()
            ->map(function ($row) {
                return $this->normalizeRow((array) $row);
            })
            ->filter(fn ($row) => !empty($row['colonia']))
            ->values();
    }

    private function normalizeRow(array $row): array
    {
        return [
            'colonia' => $row['colonia']
                ?? $row['d_asenta']
                ?? $row['asentamiento']
                ?? $row['nombre']
                ?? null,

            'tipo_asentamiento' => $row['tipo_asentamiento']
                ?? $row['d_tipo_asenta']
                ?? $row['tipo_asenta']
                ?? null,

            'municipio' => $row['municipio']
                ?? $row['D_mnpio']
                ?? $row['d_mnpio']
                ?? null,

            'estado' => $row['estado']
                ?? $row['d_estado']
                ?? null,

            'ciudad' => $row['ciudad']
                ?? $row['d_ciudad']
                ?? $row['municipio']
                ?? $row['D_mnpio']
                ?? $row['d_mnpio']
                ?? null,

            'zona' => $row['zona']
                ?? null,
        ];
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }
}