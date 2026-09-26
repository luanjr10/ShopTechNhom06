<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSpecification;

class ProductPricingService
{
    public static function unitPrice(Product $product, ?ProductSpecification $spec, ?string $sku): float
    {
        $variant = self::findVariant($spec, $sku);

        if ($variant) {
            return round((float) ($variant['price'] ?? 0), 2);
        }

        $discount = (float) ($product->discount_percent ?: 0);

        return round($product->price - ($product->price * $discount / 100), 2);
    }

    public static function stock(Product $product, ?ProductSpecification $spec, ?string $sku): int
    {
        $variant = self::findVariant($spec, $sku);

        return $variant ? (int) ($variant['stock'] ?? 0) : (int) $product->stock;
    }

    private static function findVariant(?ProductSpecification $spec, ?string $sku): ?array
    {
        if (! $sku || ! $spec) {
            return null;
        }

        foreach ($spec->variants ?? [] as $variant) {
            if (($variant['sku'] ?? null) === $sku) {
                return $variant;
            }
        }

        return null;
    }
}
