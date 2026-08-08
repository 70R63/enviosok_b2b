<?php

namespace App\Console\Commands;

use App\Services\PostalCodeCatalogImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class SyncZigoPostalCodes extends Command
{
    protected $signature = 'zigo:postal-codes:sync
        {--from=sepomex : Source: sepomex or file}
        {--file= : Absolute or project-relative CSV/TXT path}
        {--delimiter= : Single-character delimiter; auto-detected when omitted}
        {--batch=500 : Transaction batch size}';

    protected $description = 'Synchronize the canonical ZIGO postal catalog without deleting existing records';

    public function handle(PostalCodeCatalogImporter $importer): int
    {
        try {
            $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
            if ($batch === false) {
                throw new RuntimeException('--batch must be between 1 and 5000.');
            }

            $rows = match ((string) $this->option('from')) {
                'sepomex' => $this->sepomexRows(),
                'file' => $this->fileRows(),
                default => throw new RuntimeException('--from must be sepomex or file.'),
            };

            $stats = $importer->import($rows, $batch);
            $this->table(array_keys($stats), [array_values($stats)]);

            return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function sepomexRows(): iterable
    {
        if (! Schema::hasTable('sepomex')) {
            throw new RuntimeException('The sepomex source table does not exist.');
        }

        foreach (DB::table('sepomex')->orderBy('d_codigo')->orderBy('id_asenta_cpcons')->cursor() as $row) {
            yield (array) $row;
        }
    }

    private function fileRows(): iterable
    {
        $path = (string) $this->option('file');
        if ($path === '') {
            throw new RuntimeException('--file is required with --from=file.');
        }
        $path = $this->absolutePath($path);
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot read source file: {$path}");
        }

        try {
            $headerLine = fgets($handle);
            if ($headerLine === false || ! mb_check_encoding($headerLine, 'UTF-8')) {
                throw new RuntimeException('Source must be a non-empty UTF-8 CSV/TXT file.');
            }
            $delimiter = $this->delimiter($headerLine);
            $headers = array_map(fn ($value) => trim((string) $value), str_getcsv(rtrim($headerLine, "\r\n"), $delimiter));
            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count($values) !== count($headers) || ! mb_check_encoding(implode('', $values), 'UTF-8')) {
                    yield [];

                    continue;
                }
                yield array_combine($headers, $values);
            }
        } finally {
            fclose($handle);
        }
    }

    private function delimiter(string $header): string
    {
        $configured = (string) $this->option('delimiter');
        if ($configured !== '') {
            if (mb_strlen($configured) !== 1) {
                throw new RuntimeException('--delimiter must be one character.');
            }

            return $configured;
        }
        $counts = [',' => substr_count($header, ','), '|' => substr_count($header, '|'), "\t" => substr_count($header, "\t"), ';' => substr_count($header, ';')];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2})/', $path) ? $path : base_path($path);
    }
}
