<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerTierService
{
    public const TIERS = [
        'dong' => ['label' => 'Đồng', 'min_spent' => 0, 'color' => '#a16207'],
        'bac' => ['label' => 'Bạc', 'min_spent' => 10_000_000, 'color' => '#64748b'],
        'vang' => ['label' => 'Vàng', 'min_spent' => 50_000_000, 'color' => '#ca8a04'],
        'kim_cuong' => ['label' => 'Kim Cương', 'min_spent' => 200_000_000, 'color' => '#0891b2'],
    ];

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

    public function rank(string $tier): int
    {
        return array_search($tier, array_keys(self::TIERS), true) ?: 0;
    }

    public function minSpent(string $tier): float
    {
        return (float) (self::TIERS[$tier]['min_spent'] ?? 0);
    }

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
