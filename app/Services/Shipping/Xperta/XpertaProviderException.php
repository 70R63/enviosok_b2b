<?php

namespace App\Services\Shipping\Xperta;

use RuntimeException;
use Throwable;

/** A provider failure whose public diagnostics are safe to persist. */
final class XpertaProviderException extends RuntimeException
{
    public readonly array $diagnosticMetadata;
    public readonly ?int $httpStatus;
    public readonly ?string $providerMessage;
    public readonly ?string $correlationId;

    public function __construct(
        public readonly string $errorCode,
        array $diagnosticMetadata = [],
        ?Throwable $previous = null
    ) {
        if (isset($diagnosticMetadata['provider_message'])) {
            $diagnosticMetadata['provider_message'] = mb_substr((string) preg_replace(
                '/(token|api.?key|password|secret)\s*[:=]\s*[^\s,;]+/i',
                '$1=[REDACTED]',
                strip_tags((string) $diagnosticMetadata['provider_message'])
            ), 0, 300);
        }
        $this->diagnosticMetadata = $diagnosticMetadata;
        $this->httpStatus = isset($diagnosticMetadata['http_status'])
            ? (int) $diagnosticMetadata['http_status']
            : null;
        $this->providerMessage = isset($diagnosticMetadata['provider_message'])
            ? (string) $diagnosticMetadata['provider_message']
            : null;
        $this->correlationId = $diagnosticMetadata['correlation_id']
            ?? $diagnosticMetadata['request_id']
            ?? null;

        parent::__construct($errorCode, 0, $previous);
    }
}
