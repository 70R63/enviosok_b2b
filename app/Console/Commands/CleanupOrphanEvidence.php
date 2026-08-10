<?php

namespace App\Console\Commands;

use App\Domain\Shipping\LastMile\Models\LocalDeliveryFailedAttempt;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanupOrphanEvidence extends Command
{
    protected $signature = 'zigo:security:cleanup-orphan-evidence {--hours=} {--delete : Elimina después de auditar; sin esta opción sólo reporta}';
    protected $description = 'Audita y limpia evidencia POD privada huérfana con periodo de seguridad.';

    public function handle(): int
    {
        $hours = max(24, (int) ($this->option('hours') ?: config('zigo_security.evidence_orphan_hours', 48)));
        $disk = Storage::disk('local');
        $cutoff = now()->subHours($hours)->timestamp;
        $deleted = 0;
        $found = 0;

        foreach (['pod/photos', 'pod/failed', 'pod/signatures'] as $directory) {
            foreach ($disk->files($directory) as $path) {
                if ($disk->lastModified($path) > $cutoff || $this->isReferenced($path)) continue;
                $found++;
                if ($this->option('delete') && $disk->delete($path)) $deleted++;
            }
        }

        $this->info("Huérfanos elegibles: {$found}; eliminados: {$deleted}; antigüedad mínima: {$hours}h.");
        return self::SUCCESS;
    }

    private function isReferenced(string $path): bool
    {
        return LocalDeliveryProof::query()->where('photo_path', $path)->orWhere('signature_path', $path)->exists()
            || LocalDeliveryFailedAttempt::query()->where('photo_path', $path)->exists();
    }
}
