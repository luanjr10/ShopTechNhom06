<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSpecification;

/**
 * Giá & tồn kho "sự thật" của 1 sản phẩm/variant — dùng chung cho Cart và Order
 * để FE không bao giờ được tin tưởng price gửi lên. Variant không có id thật
 * trong MySQL (chỉ là JSON trong ProductSpecification.variants), nên khớp theo
 * sku. Không có sku hoặc không khớp variant nào -> dùng giá/tồn kho của product.
 */
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

    /**
     * @return array<string, mixed>|null
     */
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
