<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\ProductUseCase;
use App\Models\UseCase;

trait NormalizesProductCatalog
{
    private function calculateFinalPrice($price, $discountPercent): float
    {
        return round($price - ($price * ($discountPercent ?: 0) / 100), 2);
    }

    private function sanitizeUseCaseIds(array $useCaseIds, int $categoryId): array
    {
        $useCaseIds = array_values(array_unique(array_filter($useCaseIds)));

        if (empty($useCaseIds)) {
            return [];
        }

        return UseCase::whereIn('_id', $useCaseIds)
            ->where('categoryId', $categoryId)
            ->get()
            ->map(fn (UseCase $useCase) => (string) $useCase->id)
            ->all();
    }

    private function syncUseCases(int $productId, array $rawUseCaseIds, int $categoryId): void
    {
        $useCaseIds = $this->sanitizeUseCaseIds($rawUseCaseIds, $categoryId);

        ProductUseCase::updateOrCreate(
            ['productId' => $productId],
            ['useCaseIds' => $useCaseIds]
        );
    }

    private function normalizeVariants(array $rawVariants, Product $product): array
    {
        $variants = [];

        foreach ($rawVariants as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $attributes = [];
            foreach (['color', 'storage', 'ram', 'cpu'] as $key) {
                $value = trim((string) ($raw['attributes'][$key] ?? $raw[$key] ?? ''));
                if ($value !== '') {
                    $attributes[$key] = $value;
                }
            }

            $sku = trim((string) ($raw['sku'] ?? ''));
            $hasContent = $sku !== '' || ! empty($attributes)
                || isset($raw['price']) || isset($raw['stock']);

            if (! $hasContent) {
                continue;
            }

            $variants[] = [
                'sku' => $sku !== '' ? $sku : $this->generateSku($product, count($variants)),
                'attributes' => $attributes,
                'price' => round((float) ($raw['price'] ?? $product->price), 2),
                'stock' => (int) ($raw['stock'] ?? $product->stock),
            ];
        }

        return $variants ?: [$this->defaultVariant($product)];
    }

    private function resolveVariants(array $storedVariants, Product $product): array
    {
        return ! empty($storedVariants)
            ? $storedVariants
            : [$this->defaultVariant($product)];
    }

    private function defaultVariant(Product $product): array
    {
        return [
            'sku' => $this->generateSku($product, 0),
            'attributes' => [],
            'price' => $this->calculateFinalPrice($product->price, $product->discount_percent),
            'stock' => (int) $product->stock,
        ];
    }

    private function generateSku(Product $product, int $index): string
    {
        return $product->code.'-'.($index + 1);
    }
}
