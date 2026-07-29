<?php

namespace App\Console\Commands;

use App\Models\B2cIdentityVerification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateIdentityDocumentsToPrivate extends Command
{
    protected $signature =
        'zigo:identity:migrate-private
        {--execute : Mover realmente los documentos}';

    protected $description =
        'Previsualiza o mueve documentos de identidad '
        . 'del disco público al almacenamiento privado.';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');

        if (!$execute) {
            $this->warn(
                'MODO DRY-RUN: no se moverá ningún archivo.'
            );
        }

        $fields = [
            'ine_front',
            'ine_back',
            'selfie_with_ine',
        ];

        $summary = [
            'records' => 0,
            'files_found' => 0,
            'files_moved' => 0,
            'already_private' => 0,
            'missing' => 0,
            'errors' => 0,
        ];

        B2cIdentityVerification::query()
            ->where(function ($query) use ($fields) {
                foreach ($fields as $field) {
                    $query->orWhereNotNull($field);
                }
            })
            ->orderBy('id')
            ->chunkById(
                100,
                function ($verifications) use (
                    $execute,
                    $fields,
                    &$summary
                ) {
                    foreach ($verifications as $verification) {
                        $summary['records']++;

                        $allPrivate = true;

                        foreach ($fields as $field) {
                            $path = trim(
                                (string) $verification->{$field}
                            );

                            if ($path === '') {
                                continue;
                            }

                            if (
                                Storage::disk('local')
                                    ->exists($path)
                            ) {
                                $summary['already_private']++;
                                continue;
                            }

                            if (
                                !Storage::disk('public')
                                    ->exists($path)
                            ) {
                                $summary['missing']++;
                                $allPrivate = false;

                                $this->error(
                                    "#{$verification->id} "
                                    . "{$field}: no encontrado"
                                );

                                continue;
                            }

                            $summary['files_found']++;

                            if (!$execute) {
                                $allPrivate = false;

                                $this->line(
                                    "#{$verification->id} "
                                    . "{$field}: mover {$path}"
                                );

                                continue;
                            }

                            try {
                                $contents =
                                    Storage::disk('public')
                                        ->get($path);

                                Storage::disk('local')
                                    ->put($path, $contents);

                                if (
                                    !Storage::disk('local')
                                        ->exists($path)
                                ) {
                                    throw new \RuntimeException(
                                        'No fue posible confirmar '
                                        . 'el archivo privado.'
                                    );
                                }

                                Storage::disk('public')
                                    ->delete($path);

                                $summary['files_moved']++;
                            } catch (Throwable $exception) {
                                $summary['errors']++;
                                $allPrivate = false;

                                $this->error(
                                    "#{$verification->id} "
                                    . "{$field}: "
                                    . $exception->getMessage()
                                );
                            }
                        }

                        if ($execute && $allPrivate) {
                            $verification->update([
                                'document_disk' => 'local',
                            ]);
                        }
                    }
                }
            );

        $this->table(
            ['Métrica', 'Total'],
            collect($summary)
                ->map(
                    fn ($value, $key) => [
                        $key,
                        $value,
                    ]
                )
                ->values()
                ->all()
        );

        return $summary['errors'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}