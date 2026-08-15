<?php

namespace App\Support\Presentation;

final class DriverAddressPresenter
{
    public static function forShipment(object $shipment): array
    {
        $pickup = $shipment->status === 'READY_FOR_PICKUP';
        $snapshot = $pickup ? ($shipment->sender_snapshot ?? []) : ($shipment->recipient_snapshot ?? []);

        return self::fromSnapshot(is_array($snapshot) ? $snapshot : []) + ['pickup' => $pickup];
    }

    public static function fromSnapshot(array $snapshot): array
    {
        $nested = $snapshot['address'] ?? null;
        $details = is_array($nested) ? $nested : [];
        $preferred = data_get($snapshot, 'address.address');
        $legacy = is_string($nested) ? trim($nested) : '';
        $full = is_string($preferred) && trim($preferred) !== ''
            ? trim($preferred)
            : ($legacy !== '' ? $legacy : AddressPresenter::full($details));

        return [
            'full' => $full === '—' ? '' : $full,
            'settlement' => self::scalar($details['settlement'] ?? $snapshot['settlement'] ?? null),
            'postal_code' => self::scalar($details['postal_code'] ?? $snapshot['postal_code'] ?? null),
            'municipality' => self::scalar($details['municipality'] ?? $snapshot['municipality'] ?? null),
            'state' => self::scalar($details['state'] ?? $snapshot['state'] ?? null),
        ];
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
