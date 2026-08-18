<?php

namespace App\Support\Presentation;

final class AddressPresenter
{
    public static function address(mixed $party): mixed
    {
        if (! is_array($party)) return $party;
        return $party['address'] ?? $party;
    }

    public static function postalCode(mixed $party): string
    {
        if (! is_array($party)) return '—';
        $address = is_array($party['address'] ?? null) ? $party['address'] : [];
        return self::first($address, ['postal_code', 'codigo_postal', 'cp'])
            ?? self::first($party, ['postal_code', 'codigo_postal', 'cp']) ?? '—';
    }

    /** @return list<string> */
    public static function lines(mixed $party): array
    {
        if (! is_array($party)) return [self::full($party)];
        $address = self::address($party);
        if (! is_array($address)) return array_values(array_filter([self::first($party, ['name']), self::full($address)]));

        $street = self::first($address, ['street', 'calle']);
        $number = self::first($address, ['number', 'exterior', 'numero']);
        $interior = self::first($address, ['interior']);
        $streetLine = trim(implode(' ', array_filter([$street, $number, $interior ? 'Int. '.$interior : null])));
        $full = self::first($address, ['address']);
        if ($streetLine === '') $streetLine = $full ?? '';
        $settlement = self::first($address, ['settlement', 'colony', 'colonia']);
        $city = self::join(array_filter([self::first($address, ['municipality', 'municipio', 'alcaldia']), self::first($address, ['state', 'estado'])]));
        $postal = self::postalCode($party);
        $location = $city === '—' ? ($postal === '—' ? null : 'CP '.$postal) : $city.($postal === '—' ? '' : ' · CP '.$postal);
        return array_values(array_filter([self::first($party, ['name']), $streetLine ?: null, $settlement, $location]));
    }

    public static function full(mixed $address): string
    {
        if (is_scalar($address)) return trim((string) $address) ?: '—';
        if (! is_array($address)) return '—';
        if (isset($address['address']) && is_array($address['address'])) $address = $address['address'];
        $groups = [['street','calle'],['number','exterior','numero'],['interior'],['colony','colonia','settlement'],['postal_code','codigo_postal','cp'],['municipality','municipio','alcaldia'],['state','estado'],['country','pais']];
        $parts = [];
        foreach ($groups as $keys) if (($value = self::first($address, $keys)) !== null) $parts[] = $value;
        if ($parts === []) foreach ($address as $value) if (is_scalar($value) && trim((string) $value) !== '') $parts[] = trim((string) $value);
        return self::join($parts);
    }

    public static function compact(mixed $address): string
    {
        if (! is_array($address)) return self::full($address);
        if (isset($address['address']) && is_array($address['address'])) $address = $address['address'];
        return self::join(array_filter([self::first($address, ['municipality','municipio','alcaldia']), self::first($address, ['state','estado'])]));
    }

    private static function first(array $values, array $keys): ?string
    {
        foreach ($keys as $key) if (isset($values[$key]) && is_scalar($values[$key]) && trim((string) $values[$key]) !== '') return trim((string) $values[$key]);
        return null;
    }

    private static function join(array $parts): string
    {
        $unique = [];
        foreach ($parts as $part) { $part = trim((string) $part); if ($part !== '' && ! in_array($part, $unique, true)) $unique[] = $part; }
        return $unique === [] ? '—' : implode(', ', $unique);
    }
}
