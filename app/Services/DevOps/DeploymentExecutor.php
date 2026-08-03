<?php

namespace App\Services\DevOps;

use App\Models\ZigoDeployment;
use App\Models\ZigoDeploymentFile;
use App\Models\ZigoDeploymentLog;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DeploymentExecutor
{
    public function __construct(private PackageManifestValidator $validator) {}

    public function deploy(ZigoDeployment $deployment): void
    {
        $this->assertDeployable($deployment);
        $target = $this->targetPath($deployment->environment);
        $package = $this->packagePath($deployment);
        $staging = storage_path("app/zigo-devops/staging/{$deployment->id}-" . bin2hex(random_bytes(6)));
        $backup = storage_path("app/zigo-devops/backups/{$deployment->id}");
        $manifest = $this->validator->extractValidated($package, $staging, $deployment->environment);
        if (!hash_equals($deployment->package_sha256, $manifest['package_sha256'])
            || $deployment->package_name !== $manifest['package_name']
            || $deployment->branch !== $manifest['branch']
            || $deployment->commit_hash !== $manifest['commit_hash']) {
            $this->removeDirectory($staging);
            throw new RuntimeException('El paquete cambió después de su validación.');
        }
        $copied = false; $migrationApplied = false;

        $deployment->update(['approved_by_user_id' => auth()->id()]);
        $this->log($deployment, 'info', 'start', 'Despliegue iniciado.');

        try {
            $this->lintPhp($deployment, $staging, $manifest['files']);
            $copied = true;
            $this->backupAndCopy($deployment, $staging, $target, $backup, $manifest['files']);

            if ($manifest['migrate']) {
                $this->runArtisan($deployment, $target, ['migrate', '--force'], 'migrate');
                $migrationApplied = true;
                $this->log($deployment, 'warning', 'migrate', 'Migraciones aplicadas; no tienen rollback automático.');
            }
            foreach ($manifest['seeders'] as $seeder) {
                if (!in_array($seeder, config('zigo_devops.allowed_seeders', []), true)) { throw new RuntimeException('Seeder fuera de allowlist.'); }
                $this->runArtisan($deployment, $target, ['db:seed', '--class=Database\\Seeders\\' . $seeder, '--force'], 'seeder');
            }
            foreach ([['optimize:clear'], ['config:cache'], ['route:cache'], ['view:cache']] as $command) {
                $this->runArtisan($deployment, $target, $command, $command[0]);
            }

            $deployment->update(['status' => 'success', 'finished_at' => now(), 'backup_path' => $backup, 'rollback_available' => true, 'summary' => $this->summary($manifest, $migrationApplied)]);
            $this->log($deployment, 'info', 'finish', 'Despliegue completado.');
        } catch (Throwable $exception) {
            $this->log($deployment, 'error', 'failure', $exception->getMessage());
            if ($copied) {
                try { $this->restoreFiles($deployment, $target, $backup); $status = 'rolled_back'; }
                catch (Throwable $rollbackError) { $this->log($deployment, 'error', 'rollback', $rollbackError->getMessage()); $status = 'failed'; }
            } else { $status = 'failed'; }
            $deployment->update(['status' => $status, 'finished_at' => now(), 'backup_path' => is_dir($backup) ? $backup : null, 'rollback_available' => $status === 'failed' && is_dir($backup), 'summary' => $this->summary($manifest, $migrationApplied, $exception->getMessage())]);
            throw $exception;
        } finally {
            $this->removeDirectory($staging);
        }
    }

    public function rollback(ZigoDeployment $deployment): void
    {
        if (!in_array($deployment->status, ['success', 'failed'], true) || !$deployment->rollback_available || !$deployment->backup_path || !is_dir($deployment->backup_path)) { throw new RuntimeException('No existe un respaldo disponible.'); }
        $this->restoreFiles($deployment, $this->targetPath($deployment->environment), $deployment->backup_path);
        $deployment->update(['status' => 'rolled_back', 'rollback_available' => false, 'finished_at' => now()]);
        $this->log($deployment, 'warning', 'rollback', 'Rollback manual de archivos completado. Las migraciones no fueron revertidas.');
    }

    public function packagePath(ZigoDeployment $deployment): string
    {
        return storage_path("app/zigo-devops/packages/{$deployment->id}.zip");
    }

    private function assertDeployable(ZigoDeployment $deployment): void
    {
        if (!config('zigo_devops.enabled') || $deployment->status !== 'deploying') { throw new RuntimeException('El despliegue no está habilitado o iniciado.'); }
        if ($deployment->environment === 'production' && !config('zigo_devops.allow_production')) { throw new RuntimeException('Los despliegues a producción están deshabilitados.'); }
    }

    private function targetPath(string $environment): string
    {
        if (!in_array($environment, ['stage', 'production'], true)) { throw new RuntimeException('Ambiente inválido.'); }
        $configured = config("zigo_devops.paths.{$environment}");
        if (!is_string($configured) || trim($configured) === '') { throw new RuntimeException('La ruta del ambiente no está configurada.'); }
        $target = realpath($configured);
        if ($target === false || !is_dir($target) || !is_file($target . DIRECTORY_SEPARATOR . 'artisan')) { throw new RuntimeException('La ruta configurada no es una aplicación Laravel válida.'); }
        $root = rtrim(str_replace('\\', '/', $target), '/');
        if ($root === '' || preg_match('#^[A-Za-z]:$#', $root) || $root === '/') { throw new RuntimeException('La ruta configurada es insegura.'); }
        return $target;
    }

    private function lintPhp(ZigoDeployment $deployment, string $staging, array $files): void
    {
        $stagingRoot = realpath($staging);
        if ($stagingRoot === false || !is_dir($stagingRoot)) { throw new RuntimeException('El directorio temporal del despliegue no está disponible.'); }

        foreach ($files as $file) {
            if (!str_ends_with(strtolower($file['path']), '.php')) { continue; }
            $relative = $file['path'];
            $candidate = $stagingRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $absolute = realpath($candidate);
            if ($absolute === false || !is_file($absolute) || !$this->isWithinDirectory($absolute, $stagingRoot)) {
                throw new RuntimeException("El archivo PHP temporal no está disponible: {$relative}.");
            }

            $process = new Process([$this->phpBinary(), '-l', $absolute]);
            $process->setTimeout(30); $process->run();
            if (!$process->isSuccessful()) {
                $exitCode = $process->getExitCode() ?? -1;
                $stdout = $this->sanitizeProcessOutput($process->getOutput(), [$absolute, $stagingRoot]);
                $stderr = $this->sanitizeProcessOutput($process->getErrorOutput(), [$absolute, $stagingRoot]);
                $this->log($deployment, 'error', 'php-l', "php -l falló. Archivo: {$relative}. Código: {$exitCode}. stdout: {$stdout}. stderr: {$stderr}.");
                throw new RuntimeException("Falló php -l para {$relative}. Código: {$exitCode}.");
            }
        }
        $this->log($deployment, 'info', 'php-l', 'Sintaxis PHP validada.');
    }

    private function backupAndCopy(ZigoDeployment $deployment, string $staging, string $target, string $backup, array $files): void
    {
        $this->ensureDirectory($backup);
        foreach ($files as $file) {
                $relative = $file['path']; $source = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative); $destination = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $existed = is_file($destination); $backupFingerprint = null;
                if ($existed) {
                    $backupFile = $backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative); $this->ensureDirectory(dirname($backupFile));
                    if (!copy($destination, $backupFile)) { throw new RuntimeException("No fue posible respaldar {$relative}."); }
                    $backupFingerprint = hash_file('sha256', $backupFile);
                }
                $this->ensureDirectory(dirname($destination));
                if (!copy($source, $destination)) { throw new RuntimeException("No fue posible copiar {$relative}."); }
                ZigoDeploymentFile::updateOrCreate(['deployment_id' => $deployment->id, 'relative_path' => $relative], ['existed_before' => $existed, 'backup_fingerprint' => $backupFingerprint, 'deployed_fingerprint' => hash_file('sha256', $destination)]);
        }
        $this->log($deployment, 'info', 'copy', 'Archivos respaldados y copiados.');
    }

    private function restoreFiles(ZigoDeployment $deployment, string $target, string $backup): void
    {
        foreach ($deployment->files()->get() as $file) {
            $destination = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->relative_path);
            if ($file->deployed_fingerprint && is_file($destination) && hash_file('sha256', $destination) !== $file->deployed_fingerprint) { throw new RuntimeException("El archivo {$file->relative_path} cambió después del despliegue."); }
            if ($file->existed_before) {
                $source = $backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->relative_path);
                if (!is_file($source) || hash_file('sha256', $source) !== $file->backup_fingerprint || !copy($source, $destination)) { throw new RuntimeException("No fue posible restaurar {$file->relative_path}."); }
            } elseif (is_file($destination) && !unlink($destination)) { throw new RuntimeException("No fue posible retirar {$file->relative_path}."); }
        }
    }

    private function runArtisan(ZigoDeployment $deployment, string $target, array $arguments, string $step): void
    {
        $process = new Process(array_merge([$this->phpBinary(), 'artisan'], $arguments), $target, null, null, 300); $process->run();
        if (!$process->isSuccessful()) {
            $exitCode = $process->getExitCode() ?? -1;
            $stdout = $this->sanitizeProcessOutput($process->getOutput(), [$target]);
            $stderr = $this->sanitizeProcessOutput($process->getErrorOutput(), [$target]);
            $this->log($deployment, 'error', $step, "Proceso interno falló. Código: {$exitCode}. stdout: {$stdout}. stderr: {$stderr}.");
            throw new RuntimeException("Falló el paso interno {$step}. Código: {$exitCode}.");
        }
        $this->log($deployment, 'info', $step, "Paso interno {$step} completado.");
    }

    private function phpBinary(): string
    {
        $configured = config('zigo_devops.php_binary');
        if (!is_string($configured) || trim($configured) === '') { throw new RuntimeException('El binario PHP CLI configurado no está disponible.'); }
        $binary = realpath(trim($configured));
        if ($binary === false || !is_file($binary) || !is_executable($binary)) { throw new RuntimeException('El binario PHP CLI configurado no está disponible.'); }
        return $binary;
    }

    private function isWithinDirectory(string $path, string $directory): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedDirectory = rtrim(str_replace('\\', '/', $directory), '/');
        if (DIRECTORY_SEPARATOR === '\\') {
            $normalizedPath = strtolower($normalizedPath);
            $normalizedDirectory = strtolower($normalizedDirectory);
        }
        return str_starts_with($normalizedPath, $normalizedDirectory . '/');
    }

    private function sanitizeProcessOutput(string $output, array $paths): string
    {
        $sanitized = strip_tags($output);
        foreach (array_merge($paths, [base_path(), storage_path()]) as $path) {
            if (is_string($path) && $path !== '') { $sanitized = str_ireplace([$path, str_replace('\\', '/', $path)], '[PATH]', $sanitized); }
        }
        $sanitized = preg_replace('/(?i)(password|token|secret|api[_-]?key|authorization)\s*[:=]\s*[^\s,;]+/', '$1=[REDACTED]', $sanitized);
        return substr(trim((string) $sanitized), 0, 500);
    }

    private function log(ZigoDeployment $deployment, string $level, string $step, string $message): void
    {
        $sanitized = preg_replace('/(?i)(password|token|secret|api[_-]?key|authorization)\s*[:=]\s*[^\s,;]+/', '$1=[REDACTED]', strip_tags($message));
        ZigoDeploymentLog::create(['deployment_id' => $deployment->id, 'level' => $level, 'step' => substr($step, 0, 80), 'message' => substr((string) $sanitized, 0, 2000)]);
    }

    private function summary(array $manifest, bool $migrationApplied, ?string $error = null): string
    {
        return json_encode(['migrate' => $manifest['migrate'], 'migration_applied' => $migrationApplied, 'migrations' => $manifest['migrations'], 'seeders' => $manifest['seeders'], 'warnings' => $manifest['warnings'], 'error' => $error ? substr(strip_tags($error), 0, 500) : null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function ensureDirectory(string $path): void { if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) { throw new RuntimeException('No fue posible crear un directorio interno.'); } }
    private function removeDirectory(string $path): void { if (!is_dir($path)) return; foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); } rmdir($path); }
}
