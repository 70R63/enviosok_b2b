<?php
namespace App\Domain\AI\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
abstract class TenantAwareAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries;
    public function __construct(protected int $tenantId)
    {
        $this->onConnection((string) config('ai.queue.connection', 'database'));
        $this->onQueue((string) config('ai.queue.name', 'ai'));
        $this->tries = max(1, (int) config('ai.max_attempts', 3));
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
}
