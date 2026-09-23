<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hạng thành viên tính theo TỔNG giá trị đơn hàng ĐÃ HOÀN TẤT THÀNH CÔNG
 * (status completed) trên TOÀN SÀN — không tính riêng từng gian hàng, vì hạng
 * là chương trình khách hàng thân thiết của SÀN, không phải của seller. Đơn
 * mới 'paid' (đã thanh toán nhưng chưa giao xong) KHÔNG được tính, vì có thể
 * còn bị hủy/giao thất bại sau đó.
 *
 * Ngưỡng (đơn vị: đồng):
 * - Đồng:       0 (mặc định, mọi khách hàng mới)
 * - Bạc:        >= 10.000.000
 * - Vàng:       >= 50.000.000
 * - Kim Cương:  >= 200.000.000
 */
class CustomerTierService
{
    public const TIERS = [
        'dong' => ['label' => 'Đồng', 'min_spent' => 0, 'color' => '#a16207'],
        'bac' => ['label' => 'Bạc', 'min_spent' => 10_000_000, 'color' => '#64748b'],
        'vang' => ['label' => 'Vàng', 'min_spent' => 50_000_000, 'color' => '#ca8a04'],
        'kim_cuong' => ['label' => 'Kim Cương', 'min_spent' => 200_000_000, 'color' => '#0891b2'],
    ];

    /**
     * Đơn tính hạng — luôn dùng ở mọi nơi tính "khách đã mua bao nhiêu". CHỈ
     * tính đơn 'completed' (khách đã nhận hàng thành công) — 'paid' mới là đã
     * thanh toán, đơn có thể còn bị hủy/giao thất bại sau đó nên KHÔNG tính.
     */
    public const TIER_STATUSES = ['completed'];

    public function resolve(float $totalSpent): string
    {
        $tier = 'dong';

        foreach (self::TIERS as $key => $meta) {
            if ($totalSpent >= $meta['min_spent']) {
                $tier = $key;
            }
        }

        return $tier;
    }

    public function label(string $tier): string
    {
        return self::TIERS[$tier]['label'] ?? self::TIERS['dong']['label'];
    }

    public function color(string $tier): string
    {
        return self::TIERS[$tier]['color'] ?? self::TIERS['dong']['color'];
    }

    /** Thứ hạng theo THỨ TỰ (dong=0 thấp nhất) — dùng để so sánh "đủ điều kiện". */
    public function rank(string $tier): int
    {
        return array_search($tier, array_keys(self::TIERS), true) ?: 0;
    }

    public function minSpent(string $tier): float
    {
        return (float) (self::TIERS[$tier]['min_spent'] ?? 0);
    }

    /**
     * Khoảng chi tiêu [min, max) của 1 hạng — max=null nếu là hạng cao nhất.
     *
     * @return array{0: float, 1: ?float}
     */
    public function rangeForTier(string $tier): array
    {
        $keys = array_keys(self::TIERS);
        $index = array_search($tier, $keys, true);

        if ($index === false) {
            return [0.0, null];
        }

        $min = $this->minSpent($tier);
        $max = isset($keys[$index + 1]) ? $this->minSpent($keys[$index + 1]) : null;

        return [$min, $max];
    }

    /**
     * @return array<int, array{key:string,label:string,min_spent:float,color:string}>
     */
    public function all(): array
    {
        return collect(self::TIERS)
            ->map(fn ($meta, $key) => ['key' => $key, ...$meta])
            ->values()
            ->all();
    }

    public function totalSpentForUser(int $userId): float
    {
        return (float) Order::where('user_id', $userId)
            ->whereIn('status', self::TIER_STATUSES)
            ->sum('total_amount');
    }

    public function tierForUser(User $user): string
    {
        return $this->resolve($this->totalSpentForUser($user->id));
    }

    /**
     * Hạng kế tiếp + còn thiếu bao nhiêu để lên hạng (null nếu đã ở hạng cao nhất).
     *
     * @return array{tier:?string,label:?string,remaining:float}|null
     */
    public function nextTier(float $totalSpent): ?array
    {
        $keys = array_keys(self::TIERS);
        $current = $this->resolve($totalSpent);
        $currentIndex = array_search($current, $keys, true);

        if ($currentIndex === false || ! isset($keys[$currentIndex + 1])) {
            return null;
        }

        $nextKey = $keys[$currentIndex + 1];

        return [
            'tier' => $nextKey,
            'label' => $this->label($nextKey),
            'remaining' => max(0, $this->minSpent($nextKey) - $totalSpent),
        ];
    }

    /**
     * @param  array<int,int>  $userIds
     * @return array<int,float> user_id => total_spent
     */
    public function totalSpentForUsers(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return DB::table('orders')
            ->select('user_id')
            ->selectRaw('SUM(total_amount) as total_spent')
            ->whereIn('user_id', $userIds)
            ->whereIn('status', self::TIER_STATUSES)
            ->groupBy('user_id')
            ->pluck('total_spent', 'user_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
