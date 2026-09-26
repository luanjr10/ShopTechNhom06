<?php

namespace App\Support;

class AdminModules
{
    public const MODULES = [
        'products' => ['label' => 'Sản phẩm', 'abilities' => ['view', 'create', 'edit', 'delete']],
        'categories' => ['label' => 'Danh mục', 'abilities' => ['view', 'create', 'edit', 'delete']],
        'brands' => ['label' => 'Thương hiệu', 'abilities' => ['view', 'create', 'edit', 'delete']],
        'customers' => ['label' => 'Khách hàng', 'abilities' => ['view']],
        'seller_applications' => ['label' => 'Người bán', 'abilities' => ['view', 'edit']],
        'stores' => ['label' => 'Gian hàng', 'abilities' => ['view', 'edit']],
        'orders' => ['label' => 'Đơn hàng & Hóa đơn', 'abilities' => ['view', 'edit']],
        'reviews' => ['label' => 'Đánh giá & Theo dõi', 'abilities' => ['view', 'delete']],
        'commissions' => ['label' => 'Hoa hồng', 'abilities' => ['view', 'create', 'delete']],
        'vouchers' => ['label' => 'Voucher', 'abilities' => ['view', 'create', 'edit', 'delete']],
        'withdrawals' => ['label' => 'Rút tiền', 'abilities' => ['view', 'edit']],
        'platform_funds' => ['label' => 'Quỹ sàn', 'abilities' => ['view']],
        'home_highlights' => ['label' => 'Nổi bật trang chủ', 'abilities' => ['view', 'edit']],
    ];

    public static function keys(): array
    {
        return array_keys(self::MODULES);
    }

    public static function isValidModule(string $module): bool
    {
        return array_key_exists($module, self::MODULES);
    }

    public static function isValidAbility(string $module, string $ability): bool
    {
        return in_array($ability, self::MODULES[$module]['abilities'] ?? [], true);
    }

    public static function list(): array
    {
        return collect(self::MODULES)
            ->map(fn ($meta, $key) => ['key' => $key, ...$meta])
            ->values()
            ->all();
    }
}
