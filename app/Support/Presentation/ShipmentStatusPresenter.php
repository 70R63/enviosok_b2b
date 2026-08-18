<?php

namespace App\Support\Presentation;

final class ShipmentStatusPresenter
{
    public const LABELS = [
        'CREATED' => 'Envío creado',
        'READY_FOR_PICKUP' => 'Recolección solicitada',
        'PICKED_UP' => 'Recolectado',
        'IN_TRANSIT' => 'En tránsito',
        'OUT_FOR_DELIVERY' => 'En reparto',
        'DELIVERED' => 'Entregado',
        'DELIVERY_FAILED' => 'No entregado',
        'CANCELED' => 'Cancelado',
    ];

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? 'Actualización de envío';
    }
}
