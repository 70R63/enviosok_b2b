<?php
namespace App\Domain\AI\Support;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use InvalidArgumentException;
final class TenantKnowledgeStorage
{
    public function __construct(
        private KnowledgeStorageGuard $guard,
        private AiTenantBoundary $tenants,
        private FilesystemFactory $filesystems,
    ) {}
    public function path(string $relativePath): string
    {
        $relativePath = $this->validateRelativePath($relativePath);
        return 'tenants/'.$this->tenants->requireTenant()->getKey().'/ai/knowledge/'.$relativePath;
    }
    public function put(string $relativePath, string $contents): bool
    {
        return $this->disk()->put($this->path($relativePath), $contents);
    }
    public function get(string $relativePath): string
    {
        return $this->disk()->get($this->path($relativePath));
    }
    public function exists(string $relativePath): bool
    {
        return $this->disk()->exists($this->path($relativePath));
    }
    public function delete(string $relativePath): bool
    {
        return $this->disk()->delete($this->path($relativePath));
    }
    private function disk()
    {
        return $this->filesystems->disk($this->guard->disk());
    }
    private function validateRelativePath(string $path): string
    {
        if ($path === '' || $path !== trim($path)) throw new InvalidArgumentException('Knowledge path must be a non-empty normalized relative path.');
        if (str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) throw new InvalidArgumentException('Knowledge path must be relative and use forward slashes.');
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) throw new InvalidArgumentException('Knowledge path contains control characters.');
        $segments = explode('/', $path);
        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) throw new InvalidArgumentException('Knowledge path contains unsafe segments.');
        return implode('/', $segments);
    }
}
