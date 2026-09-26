<?php

namespace App\Services\Shipping;

interface ShippingProviderInterface
{
    public function calculateFee(array $params): array;

    public function createShipment(array $params): array;
}
