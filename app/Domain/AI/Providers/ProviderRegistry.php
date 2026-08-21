<?php
namespace App\Domain\AI\Providers;
use App\Domain\AI\Core\AiFeatureGate;
use App\Domain\AI\Runtime\Contracts\EmbeddingGateway;
use App\Domain\AI\Runtime\Contracts\ModelGateway;
use Illuminate\Contracts\Container\Container;
use LogicException;
final class ProviderRegistry
{
    public function __construct(private Container $container, private AiFeatureGate $featureGate) {}
    public function model(): ModelGateway { return $this->resolve(ModelGateway::class); }
    public function embeddings(): EmbeddingGateway { return $this->resolve(EmbeddingGateway::class); }
    private function resolve(string $contract): mixed
    {
        $this->featureGate->ensureEnabled();
        if (! $this->container->bound($contract)) throw new LogicException("No AI provider is configured for {$contract}.");
        return $this->container->make($contract);
    }
}
