<?php

namespace App\Services\Shipping\Xperta;

class XpertaExternalMessageSanitizer
{
    private const GENERIC_MESSAGE = 'Respuesta de Xperta no disponible.';

    public static function sanitize(?string $message): string
    {
        if ($message === null) {
            return self::GENERIC_MESSAGE;
        }

        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? '';
        $message = preg_replace(
            '/\b(authorization|x-api-key|token|password)\b\s*[:=]\s*'
            . '(?:Bearer\s+[A-Za-z0-9._~+\/-]+=*|"[^"]*"|'
            . '\'[^\']*\'|[^,;\s}\]]+)/iu',
            '$1=[REDACTED]',
            $message
        ) ?? '';
        $message = preg_replace(
            '/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/iu',
            'Bearer [REDACTED]',
            $message
        ) ?? '';
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? '');

        if ($message === '') {
            return self::GENERIC_MESSAGE;
        }

        return mb_substr($message, 0, 250);
    }
}
