<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\ProductUseCase;
use App\Models\UseCase;

/**
 * Logic chuẩn hoá catalog dùng chung giữa ProductController (admin) và
 * SellerProductController (seller quản lý sản phẩm trong gian hàng), để hai
 * kênh tạo/sửa sản phẩm cho ra cùng một cấu trúc variants/use_case/giá.
 */
trait NormalizesProductCatalog
{
    private function calculateFinalPrice($price, $discountPercent): float
    {
        return round($price - ($price * ($discountPercent ?: 0) / 100), 2);
    }

    /**
     * Lọc danh sách _id Quick Link hợp lệ: tồn tại và thuộc đúng danh mục sản phẩm.
     *
     * @param  array<int, string>  $useCaseIds
     * @return array<int, string>
     */
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

    /**
     * Lưu Quick Link đã gán cho sản phẩm sau khi lọc theo đúng danh mục.
     *
     * @param  array<int, string>  $rawUseCaseIds
     */
    private function syncUseCases(int $productId, array $rawUseCaseIds, int $categoryId): void
    {
        $useCaseIds = $this->sanitizeUseCaseIds($rawUseCaseIds, $categoryId);

        ProductUseCase::updateOrCreate(
            ['productId' => $productId],
            ['useCaseIds' => $useCaseIds]
        );
    }

    /**
     * Chuẩn hoá danh sách variants nhận từ request trước khi lưu vào MongoDB.
     * Bỏ qua variant rỗng; chỉ giữ các thuộc tính có giá trị (color/storage/ram/cpu).
     * Nếu không có variant hợp lệ nào thì tạo 1 variant mặc định từ sản phẩm.
     *
     * @param  array<int, mixed>  $rawVariants
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * Trả về variants để hiển thị: dùng dữ liệu đã lưu, nếu trống thì 1 variant mặc định.
     *
     * @param  array<int, mixed>  $storedVariants
     * @return array<int, array<string, mixed>>
     */
    private function resolveVariants(array $storedVariants, Product $product): array
    {
        return ! empty($storedVariants)
            ? $storedVariants
            : [$this->defaultVariant($product)];
    }

    /**
     * Variant mặc định cho sản phẩm chỉ có 1 phiên bản.
     *
     * @return array<string, mixed>
     */
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
