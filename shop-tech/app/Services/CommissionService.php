<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CommissionSetting;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\Store;

class CommissionService
{
    public function resolveRate(Store $store, ?int $categoryId = null): float
    {
        $storeRate = CommissionSetting::where('scope', 'store')
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->value('rate');

        if ($storeRate !== null) {
            return (float) $storeRate;
        }

        if ($categoryId !== null) {
            $categoryRate = CommissionSetting::where('scope', 'category')
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->value('rate');

            if ($categoryRate !== null) {
                return (float) $categoryRate;
            }
        }

        $defaultRate = CommissionSetting::where('scope', 'default')
            ->where('is_active', true)
            ->value('rate');

        return (float) ($defaultRate ?? 0);
    }

    /**
     * @param  array<int, array{store: Store, subtotal: float, category_id: int|null}>  $groups
     */
    public function estimateTotal(array $groups): float
    {
        $total = 0.0;

        foreach ($groups as $group) {
            $total += $group['subtotal'] * $this->resolveRate($group['store'], $group['category_id']) / 100;
        }

        return round($total, 2);
    }

    public function estimateForCart(Cart $cart): float
    {
        return $this->estimateTotal($this->cartGroups($cart));
    }

    /**
     * @return array<int, float> store_id => tạm tính của gian hàng trong giỏ
     */
    public function storeSubtotalsForCart(Cart $cart): array
    {
        return array_map(fn (array $group) => $group['subtotal'], $this->cartGroups($cart));
    }

    /**
     * @return array<int, array{store: Store, subtotal: float, category_id: int|null}>
     */
    private function cartGroups(Cart $cart): array
    {
        $items = $cart->items()->get();
        $productIds = $items->pluck('product_id')->unique()->all();
        $products = Product::with('store')->whereIn('id', $productIds)->get()->keyBy('id');
        $specs = ProductSpecification::whereIn('productId', $productIds)->get()->keyBy('productId');

        $byStore = [];
        foreach ($items as $item) {
            $product = $products->get($item->product_id);
            if (! $product || (int) $product->status !== 1 || ! $product->store) {
                continue;
            }

            $unitPrice = ProductPricingService::unitPrice($product, $specs->get($product->id), $item->sku ?: null);
            $byStore[$product->store_id]['store'] = $product->store;
            $byStore[$product->store_id]['subtotal'] = ($byStore[$product->store_id]['subtotal'] ?? 0) + $unitPrice * $item->quantity;
            $byStore[$product->store_id]['categories'][] = $product->category_id;
        }

        return array_map(function (array $group) {
            $categories = array_unique($group['categories']);

            return [
                'store' => $group['store'],
                'subtotal' => (float) $group['subtotal'],
                'category_id' => count($categories) === 1 ? (int) $categories[0] : null,
            ];
        }, $byStore);
    }
}
