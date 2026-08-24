<?php

namespace App\Domain\AI\Actions\Data;

use App\Domain\AI\Actions\Contracts\ActionHandler;
use App\Domain\AI\Actions\Enums\ActionConfirmationPolicy;
use App\Domain\AI\Actions\Enums\ActionEffect;
use App\Domain\AI\Runtime\Support\ProviderOutputSchemaGuard;

final readonly class ActionDefinition
{
    public function __construct(
        public string $key,
        public string $displayName,
        public string $description,
        public array $inputSchema,
        public array $outputSchema,
        public ActionEffect $effect,
        public ActionConfirmationPolicy $confirmation,
        public ActionHandler $handler,
    ) {
        if (! preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $key) || trim($displayName) === '' || mb_strlen($displayName) > 100 || trim($description) === '' || mb_strlen($description) > 500) {
            throw new \InvalidArgumentException('Invalid Action definition.');
        }
        if ($effect === ActionEffect::Write && $confirmation !== ActionConfirmationPolicy::Required) {
            throw new \InvalidArgumentException('Write Actions require server-side confirmation.');
        }
        ProviderOutputSchemaGuard::validate($inputSchema);
        ProviderOutputSchemaGuard::validate($outputSchema);
    }
}
