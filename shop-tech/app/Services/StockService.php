<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSpecification;
use RuntimeException;

class StockService
{
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

        Product::where('id', $productId)->increment('stock', $quantity);
    }
}
