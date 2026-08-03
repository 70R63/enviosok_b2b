<?php

namespace App\Services\DevOps;

use Illuminate\Validation\ValidationException;
use ZipArchive;

class PackageManifestValidator
{
    private const ROOTS = ['app/', 'bootstrap/', 'config/', 'database/migrations/', 'database/seeders/', 'public/', 'resources/', 'routes/'];
    private const BLOCKED_PARTS = ['.env', 'vendor', 'storage', 'node_modules', '.git'];
    private const BLOCKED_EXTENSIONS = ['exe', 'com', 'bat', 'cmd', 'ps1', 'sh', 'bash', 'zsh', 'fish', 'phar', 'msi', 'dll', 'so'];

    public function validate(string $zipPath, string $environment): array
    {
        $this->failUnless(is_file($zipPath), 'El paquete ZIP no existe.');
        $this->failUnless(strtolower(pathinfo($zipPath, PATHINFO_EXTENSION)) === 'zip', 'Solo se permiten paquetes ZIP.');
        $maxBytes = max(1, (int) config('zigo_devops.max_package_mb', 25)) * 1024 * 1024;
        $this->failUnless(filesize($zipPath) <= $maxBytes, 'El paquete excede el tamaño máximo configurado.');

        $zip = new ZipArchive();
        $this->failUnless($zip->open($zipPath, ZipArchive::RDONLY) === true, 'No fue posible abrir el ZIP.');

        try {
            $this->failUnless($zip->numFiles > 0 && $zip->numFiles <= 2000, 'El ZIP está vacío o contiene demasiadas entradas.');
            $entries = [];
            $totalSize = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $this->failUnless(is_array($stat), 'No fue posible inspeccionar una entrada del ZIP.');
                $raw = (string) $stat['name'];
                $normalized = str_replace('\\', '/', $raw);
                $this->failUnless(
                    !str_starts_with($normalized, '/')
                        && !preg_match('/^[A-Za-z]:/', $normalized),
                    "Ruta absoluta no permitida: {$normalized}"
                );
                $path = trim($normalized, '/');
                if ($path === '') { continue; }
                $this->failUnless(!$this->isSymlink($zip, $index), "No se permiten enlaces simbólicos: {$path}");

                if ($this->isDirectory($zip, $index, $normalized)) {
                    $this->validateDirectoryPath($path);
                    continue;
                }

                $this->failUnless(!str_contains($raw, '\\'), "El archivo contiene separadores Windows no permitidos: {$raw}");
                $this->validatePath($path);
                $this->failUnless(!$this->isExecutable($zip, $index), "No se permiten archivos ejecutables: {$path}");
                $totalSize += (int) ($stat['size'] ?? 0);
                $this->failUnless($totalSize <= $maxBytes * 4, 'El contenido descomprimido excede el límite de seguridad.');
                $entries[$path] = $index;
            }

            $this->failUnless(isset($entries['deploy-manifest.json']), 'Falta deploy-manifest.json en la raíz.');
            $manifestRaw = $zip->getFromIndex($entries['deploy-manifest.json']);
            $this->failUnless(is_string($manifestRaw) && strlen($manifestRaw) <= 262144, 'El manifiesto es inválido o demasiado grande.');
            $manifest = json_decode($manifestRaw, true);
            $this->failUnless(is_array($manifest) && json_last_error() === JSON_ERROR_NONE, 'deploy-manifest.json no contiene JSON válido.');

            $allowedKeys = ['package_name', 'environment', 'branch', 'commit_hash', 'files', 'migrate', 'migrations', 'seeders'];
            $this->failUnless(array_diff(array_keys($manifest), $allowedKeys) === [], 'El manifiesto contiene propiedades no permitidas.');
            $this->failUnless(is_string($manifest['package_name'] ?? null) && preg_match('/^[A-Za-z0-9._-]{1,120}$/', $manifest['package_name']), 'package_name no es válido.');
            $this->failUnless(($manifest['environment'] ?? $environment) === $environment, 'El ambiente del manifiesto no coincide con el solicitado.');
            $this->validateRevision($manifest);
            $this->failUnless(is_bool($manifest['migrate'] ?? null), 'migrate debe ser booleano.');
            $this->failUnless(is_array($manifest['files'] ?? null), 'files debe ser una lista.');
            $this->failUnless(is_array($manifest['migrations'] ?? null), 'migrations debe ser una lista.');
            $this->failUnless(is_array($manifest['seeders'] ?? null), 'seeders debe ser una lista.');

            $declared = [];
            foreach ($manifest['files'] as $file) {
                $this->failUnless(is_array($file) && count($file) === 2 && array_diff(array_keys($file), ['path', 'sha256']) === [] && isset($file['path'], $file['sha256']), 'Cada archivo debe declarar únicamente path y sha256.');
                $path = (string) ($file['path'] ?? '');
                $sha = strtolower((string) ($file['sha256'] ?? ''));
                $this->validatePath($path);
                $this->failUnless($path !== 'deploy-manifest.json' && preg_match('/^[a-f0-9]{64}$/', $sha), "SHA-256 inválido para {$path}.");
                $this->failUnless(isset($entries[$path]) && !isset($declared[$path]), "Archivo faltante o duplicado: {$path}");
                $contents = $zip->getFromIndex($entries[$path]);
                $this->failUnless(is_string($contents) && hash('sha256', $contents) === $sha, "SHA-256 no coincide para {$path}.");
                $declared[$path] = $sha;
            }

            $actual = array_diff(array_keys($entries), ['deploy-manifest.json']);
            sort($actual); $declaredPaths = array_keys($declared); sort($declaredPaths);
            $this->failUnless($actual === $declaredPaths, 'La lista de archivos no coincide con el contenido real del ZIP.');
            $this->validateMigrationsAndSeeders($manifest, $declaredPaths);

            return [
                'package_name' => $manifest['package_name'],
                'environment' => $environment,
                'branch' => $manifest['branch'] ?? null,
                'commit_hash' => $manifest['commit_hash'] ?? null,
                'package_sha256' => hash_file('sha256', $zipPath),
                'files' => $manifest['files'],
                'migrate' => $manifest['migrate'],
                'migrations' => array_values($manifest['migrations']),
                'seeders' => array_values($manifest['seeders']),
                'warnings' => $manifest['migrate'] ? ['Las migraciones no tienen rollback automático.'] : [],
            ];
        } finally {
            $zip->close();
        }
    }

    public function extractValidated(string $zipPath, string $destination, string $environment): array
    {
        $manifest = $this->validate($zipPath, $environment);
        if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw ValidationException::withMessages(['package' => 'No fue posible crear staging temporal.']);
        }
        $zip = new ZipArchive(); $zip->open($zipPath, ZipArchive::RDONLY);
        try {
            foreach ($manifest['files'] as $file) {
                $path = $file['path']; $target = $destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
                $directory = dirname($target);
                if (!is_dir($directory)) { mkdir($directory, 0700, true); }
                $source = $zip->getStream($path); $output = fopen($target, 'wb');
                $this->failUnless(is_resource($source) && is_resource($output), "No fue posible extraer {$path}.");
                stream_copy_to_stream($source, $output); fclose($source); fclose($output);
            }
        } finally { $zip->close(); }
        return $manifest;
    }

    private function validatePath(string $path): void
    {
        $this->failUnless($path !== '' && !str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:/', $path), "Ruta absoluta no permitida: {$path}");
        $parts = explode('/', $path);
        $this->failUnless(!in_array('..', $parts, true) && !in_array('.', $parts, true), "Ruta relativa insegura: {$path}");
        foreach ($parts as $part) {
            $lower = strtolower($part);
            $this->failUnless($part !== '' && !in_array($lower, self::BLOCKED_PARTS, true) && !str_starts_with($lower, '.env'), "Ruta bloqueada: {$path}");
        }
        if ($path !== 'deploy-manifest.json') {
            $this->failUnless(collect(self::ROOTS)->contains(fn (string $root): bool => str_starts_with($path, $root)), "Ruta fuera de allowlist: {$path}");
            $this->failUnless(!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::BLOCKED_EXTENSIONS, true), "Tipo de archivo bloqueado: {$path}");
        }
    }

    private function validateDirectoryPath(string $path): void
    {
        $this->failUnless(
            $path !== ''
                && !str_starts_with($path, '/')
                && !preg_match('/^[A-Za-z]:/', $path),
            "Ruta absoluta no permitida: {$path}"
        );

        $parts = explode('/', $path);
        $this->failUnless(
            !in_array('..', $parts, true)
                && !in_array('.', $parts, true),
            "Ruta relativa insegura: {$path}"
        );
    }

    private function validateRevision(array $manifest): void
    {
        $branch = $manifest['branch'] ?? null; $commit = $manifest['commit_hash'] ?? null;
        $this->failUnless($branch === null || (is_string($branch) && preg_match('/^[A-Za-z0-9._\/-]{1,120}$/', $branch) && !str_contains($branch, '..')), 'branch no es válido.');
        $this->failUnless($commit === null || (is_string($commit) && preg_match('/^[a-fA-F0-9]{7,40}$/', $commit)), 'commit_hash no es válido.');
    }

    private function validateMigrationsAndSeeders(array $manifest, array $files): void
    {
        foreach ($manifest['migrations'] as $migration) {
            $this->failUnless(is_string($migration) && str_starts_with($migration, 'database/migrations/') && str_ends_with($migration, '.php') && in_array($migration, $files, true), 'Migración declarada inválida.');
        }
        $actualMigrations = array_values(array_filter($files, fn (string $path): bool => str_starts_with($path, 'database/migrations/')));
        $this->failUnless($manifest['migrate'] || $actualMigrations === [], 'El paquete incluye migraciones pero migrate es falso.');
        $this->failUnless(array_diff($actualMigrations, $manifest['migrations']) === [], 'Hay migraciones no declaradas.');
        foreach ($manifest['seeders'] as $seeder) {
            $this->failUnless(is_string($seeder) && in_array($seeder, config('zigo_devops.allowed_seeders', []), true), 'Seeder no permitido.');
            $this->failUnless(in_array("database/seeders/{$seeder}.php", $files, true), 'El archivo del seeder declarado no existe.');
        }
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0; $attributes = 0;
        return $zip->getExternalAttributesIndex($index, $opsys, $attributes) && (($attributes >> 16) & 0170000) === 0120000;
    }

    private function isDirectory(
        ZipArchive $zip,
        int $index,
        string $normalizedName
    ): bool {
        if (str_ends_with($normalizedName, '/')) {
            return true;
        }

        $opsys = 0;
        $attributes = 0;

        if (!$zip->getExternalAttributesIndex(
            $index,
            $opsys,
            $attributes
        )) {
            return false;
        }

        if ($opsys === ZipArchive::OPSYS_UNIX
            && (($attributes >> 16) & 0170000) === 0040000) {
            return true;
        }

        return ($attributes & 0x10) === 0x10;
    }

    private function isExecutable(ZipArchive $zip, int $index): bool
    {
        $opsys = 0; $attributes = 0;
        return $zip->getExternalAttributesIndex($index, $opsys, $attributes)
            && $opsys === ZipArchive::OPSYS_UNIX
            && (($attributes >> 16) & 0111) !== 0;
    }

    private function failUnless(bool $condition, string $message): void
    {
        if (!$condition) { throw ValidationException::withMessages(['package' => $message]); }
    }
}
