<?php
namespace App\Domain\AI\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Domain\Network\Tenancy\TenantContext;
use Throwable;
abstract class TenantAwareAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries;
    public int $timeout;
    public function __construct(protected int $tenantId)
    {
        $this->onConnection($this->configuredName('ai.queue.connection', 'database'));
        $this->onQueue($this->configuredName('ai.queue.name', 'ai'));
        $this->tries = $this->configuredInt('ai.max_attempts', 3, 1, 10);
        $this->timeout = $this->configuredInt('ai.job_timeout', 120, 1, 900);
    }
    final public function handle(AiTenantJobExecution $execution, Container $container): mixed
    {
        return $execution->run($this->tenantId, fn () => $this->execute($container));
    }
    abstract protected function execute(Container $container): mixed;

    final public function tenantId(): int
    {
        return $this->tenantId;
    }

    final public function backoff(): array
    {
        $value = config('ai.job_backoff_seconds', '10,30,60');
        $items = is_array($value) ? $value : explode(',', (string) $value);
        if ($items === []) return [10, 30, 60];
        $result = [];
        foreach ($items as $item) {
            if (filter_var($item, FILTER_VALIDATE_INT) === false) return [10, 30, 60];
            $seconds = (int) $item;
            if ($seconds < 1 || $seconds > 3600) return [10, 30, 60];
            $result[] = $seconds;
        }
        return $result ?: [10, 30, 60];
    }

    final public function failed(Throwable $exception): void
    {
        app(TenantContext::class)->clear();
    }

    private function configuredInt(string $key, int $default, int $min, int $max): int
    {
        $value = filter_var(config($key, $default), FILTER_VALIDATE_INT);
        return $value !== false && $value >= $min && $value <= $max ? $value : $default;
    }

    private function configuredName(string $key, string $default): string
    {
        $value = trim((string) config($key, $default));
        return $value !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $value) ? $value : $default;
    }
}
