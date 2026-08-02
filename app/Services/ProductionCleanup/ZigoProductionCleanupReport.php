<?php

namespace App\Services\ProductionCleanup;

use Illuminate\Support\Facades\Storage;

class ZigoProductionCleanupReport
{
    public function save(array $report): string
    {
        $directory = 'private/prd-cleanup-reports';
        $filename = now()->format('Ymd_His_u')
            . '_' . ($report['mode'] ?? 'dry-run') . '.json';
        $path = $directory . '/' . $filename;

        Storage::disk('local')->put(
            $path,
            json_encode(
                $report,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            )
        );

        return storage_path('app/' . $path);
    }
}
