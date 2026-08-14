<?php

namespace App\Support\Network;

final class ModulePresentation
{
    private const LABELS = [
        'WHITE_LABEL' => 'Portal bajo tu marca',
        'CUSTOMERS' => 'Portal y clientes',
        'QUOTES' => 'Cotización y envíos',
        'B2C' => 'Portal y clientes',
        'SHIPPING' => 'Cotización y envíos',
        'TRACKING' => 'Tracking',
        'CRM' => 'CRM',
        'LOCAL_SHIPPING' => 'Envíos locales',
        'DRIVER' => 'ZIGO Driver',
        'SUPPORT' => 'Soporte',
        'API' => 'API Hub e integraciones',
    ];

    public static function label(string $code, ?string $fallback = null): string
    {
        return self::LABELS[strtoupper($code)] ?? $fallback ?? $code;
    }

    public static function labels(iterable $modules): array
    {
        $labels = [];
        foreach ($modules as $module) {
            $label = self::label((string) $module->code, (string) $module->name);
            $labels[$label] = $label;
        }

        return array_values($labels);
    }
}
