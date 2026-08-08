<?php

namespace App\Services;

use App\Models\ZigoPostalCode;
use Illuminate\Support\Facades\DB;

final class PostalCodeCatalogImporter
{
    public function import(iterable $rows, int $batchSize = 500): array
    {
        $stats = ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'errors' => 0];
        $batch = [];

        foreach ($rows as $source) {
            $stats['processed']++;
            $mapped = $this->map((array) $source);
            if ($mapped === null) {
                $stats['errors']++;

                continue;
            }
            $batch[] = $mapped;
            if (count($batch) >= $batchSize) {
                $this->persist($batch, $stats);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->persist($batch, $stats);
        }

        return $stats;
    }

    public function map(array $row): ?array
    {
        $value = static fn (array $keys) => collect($keys)
            ->map(fn ($key) => $row[$key] ?? null)
            ->first(fn ($item) => $item !== null && trim((string) $item) !== '');

        $postalCode = trim((string) $value(['codigo_postal', 'd_codigo', 'cp', 'postal_code']));
        $state = trim((string) $value(['estado', 'd_estado']));
        $municipality = trim((string) $value(['municipio', 'd_mnpio', 'D_mnpio']));
        $settlement = trim((string) $value(['asentamiento', 'd_asenta', 'colonia']));

        if (! preg_match('/^\d{5}$/', $postalCode) || $state === '' || $municipality === '' || $settlement === '') {
            return null;
        }

        return [
            'codigo_postal' => $postalCode,
            'estado' => $state,
            'municipio' => $municipality,
            'ciudad' => $this->nullable($value(['ciudad', 'd_ciudad'])),
            'asentamiento' => $settlement,
            'tipo_asentamiento' => $this->nullable($value(['tipo_asentamiento', 'd_tipo_asenta'])),
            'zona' => $this->nullable($value(['zona', 'd_zona'])),
            'cobertura_estafeta' => $this->boolean($value(['cobertura_estafeta']), true),
            'activo' => $this->boolean($value(['activo']), true),
        ];
    }

    private function persist(array $batch, array &$stats): void
    {
        DB::transaction(function () use ($batch, &$stats) {
            foreach ($batch as $row) {
                $identity = collect($row)->only(['codigo_postal', 'estado', 'municipio', 'asentamiento'])->all();
                $model = ZigoPostalCode::query()->firstOrNew($identity);
                $exists = $model->exists;
                $model->fill($row)->save();
                $stats[$exists ? 'updated' : 'inserted']++;
            }
        });
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function boolean($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
