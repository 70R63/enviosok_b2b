<?php
namespace App\Domain\AI\Providers;
use App\Domain\AI\Core\AiFeatureGate;
use App\Domain\AI\Runtime\Contracts\EmbeddingGateway;
use App\Domain\AI\Runtime\Contracts\ModelGateway;
use Illuminate\Contracts\Container\Container;
use LogicException;
use App\Domain\AI\Runtime\Exceptions\ModelProviderNotConfiguredException;
use App\Domain\AI\Runtime\Providers\OpenAIResponsesModelGateway;
final class ProviderRegistry
{
    public function __construct(private Container $container, private AiFeatureGate $featureGate) {}
    public function model(): ModelGateway
    {
        $this->featureGate->ensureEnabled();
        return match (config('ai.default_provider')) {
            'openai' => $this->container->make(OpenAIResponsesModelGateway::class),
            default => throw new ModelProviderNotConfiguredException('The model provider is not configured.'),
        };
    }
    public function modelConfigured(): bool
    {
        $key = config('ai.providers.openai.api_key');
        $model = config('ai.providers.openai.model');
        return config('ai.default_provider') === 'openai'
            && config('ai.providers.openai.endpoint') === 'https://api.openai.com/v1/responses'
            && is_string($model) && in_array($model, config('ai.providers.openai.approved_models', []), true)
            && is_string($key) && $key !== '' && trim($key) === $key && ! preg_match('/[\x00-\x20\x7F]/', $key);
    }
    public function embeddings(): EmbeddingGateway { return $this->resolve(EmbeddingGateway::class); }
    private function resolve(string $contract): mixed
    {
        $this->featureGate->ensureEnabled();
        if (! $this->container->bound($contract)) throw new LogicException("No AI provider is configured for {$contract}.");
        return $this->container->make($contract);
    }
}
