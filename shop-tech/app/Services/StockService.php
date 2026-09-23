<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSpecification;
use RuntimeException;

/**
 * Trừ/hoàn kho (products.stock hoặc variant stock trong Mongo). Kho chỉ bị
 * trừ THẬT khi seller bàn giao vận chuyển (decrement(), gọi từ SellerOrderService
 * ::handover() — KHÔNG trừ lúc đặt hàng nữa, xem OrderService::place) và hoàn
 * lại (restore()) khi 1 đơn ĐÃ bàn giao bị hủy (SellerOrderService::cancel()).
 */
class StockService
{
    /**
     * Trừ kho lúc bàn giao — ném RuntimeException nếu không đủ hàng (có thể
     * xảy ra vì nhiều đơn cùng 'pending'/'confirmed' trên 1 sản phẩm chưa bị
     * trừ kho, chỉ phát hiện thiếu hàng tại đây thay vì lúc đặt hàng).
     */
    public function decrement(int $productId, ?string $sku, int $quantity, string $productName): void
    {
        if ($sku) {
            $spec = ProductSpecification::where('productId', $productId)->first();
            $variants = $spec?->variants ?? [];
            $matched = false;

            foreach ($variants as &$variant) {
                if (($variant['sku'] ?? null) === $sku) {
                    $current = (int) ($variant['stock'] ?? 0);
                    if ($current < $quantity) {
                        throw new RuntimeException("Sản phẩm \"{$productName}\" không đủ tồn kho để bàn giao (còn {$current}).");
                    }
                    $variant['stock'] = $current - $quantity;
                    $matched = true;
                }
            }
            unset($variant);

            if ($matched) {
                $spec->update(['variants' => $variants]);

                return;
            }
        }

        $product = Product::where('id', $productId)->lockForUpdate()->first();
        if (! $product || (int) $product->stock < $quantity) {
            $available = $product ? (int) $product->stock : 0;
            throw new RuntimeException("Sản phẩm \"{$productName}\" không đủ tồn kho để bàn giao (còn {$available}).");
        }
        $product->decrement('stock', $quantity);
    }

    public function restore(int $productId, ?string $sku, int $quantity): void
    {
        if ($sku) {
            $spec = ProductSpecification::where('productId', $productId)->first();
            $variants = $spec?->variants ?? [];
            $matched = false;

            foreach ($variants as &$variant) {
                if (($variant['sku'] ?? null) === $sku) {
                    $variant['stock'] = (int) ($variant['stock'] ?? 0) + $quantity;
                    $matched = true;
                }
            }
            unset($variant);

            if ($matched) {
                $spec->update(['variants' => $variants]);

                return;
            }
        }

        // Không có sku, hoặc sku là mã mặc định được sinh lúc hiển thị (không có
        // trong Mongo) — xem bẫy tương tự ở ProductPricingService/CartController.
        Product::where('id', $productId)->increment('stock', $quantity);
    }
}
