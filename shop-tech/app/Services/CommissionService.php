<?php

namespace App\Services;

use App\Models\CommissionSetting;
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
}
