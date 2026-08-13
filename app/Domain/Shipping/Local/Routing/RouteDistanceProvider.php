<?php

namespace App\Domain\Shipping\Local\Routing;

interface RouteDistanceProvider
{
    /** @return array{distance_meters:int,duration_seconds:?int}|null */
    public function distance(string $originAddress, string $destinationAddress): ?array;
}
