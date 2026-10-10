<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\PriceAlertService;

class ProductPriceObserver
{
    public function __construct(private PriceAlertService $priceAlerts) {}

    public function updated(Product $product): void
    {
        if (! $product->wasChanged(['price', 'discount_percent'])) {
            return;
        }

        $oldPrice = PriceAlertService::effectivePrice(
            (float) $product->getOriginal('price'),
            (float) ($product->getOriginal('discount_percent') ?: 0),
        );
        $newPrice = PriceAlertService::effectivePrice((float) $product->price, (float) ($product->discount_percent ?: 0));

        $this->priceAlerts->notifyWatchers($product, $oldPrice, $newPrice);
    }
}
