<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Wishlist;
use App\Notifications\PriceDropNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class PriceAlertService
{
    public static function effectivePrice(float $price, float $discountPercent): float
    {
        return round($price - ($price * $discountPercent / 100), 2);
    }

    public function notifyWatchers(Product $product, float $oldPrice, float $newPrice): int
    {
        if ($newPrice >= $oldPrice) {
            return 0;
        }

        $notified = 0;

        Wishlist::with('user')->where('product_id', $product->id)->get()->each(
            function (Wishlist $item) use ($product, $oldPrice, $newPrice, &$notified) {
                if ($item->target_price !== null && $newPrice > (float) $item->target_price) {
                    return;
                }

                if ($item->last_notified_price !== null && $newPrice >= (float) $item->last_notified_price) {
                    return;
                }

                try {
                    $item->user->notify(new PriceDropNotification($product, $oldPrice, $newPrice));
                } catch (Throwable $e) {
                    Log::warning('Không gửi được mail báo giảm giá', ['wishlist_id' => $item->id, 'error' => $e->getMessage()]);

                    return;
                }

                $item->update(['last_notified_price' => $newPrice]);
                $notified++;
            }
        );

        return $notified;
    }
}
