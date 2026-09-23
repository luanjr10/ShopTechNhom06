<?php

namespace App\Providers;

use App\Services\Shipping\GhnProvider;
use App\Services\Shipping\ShippingProviderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Provider vận chuyển hiện tại: GHN. Đổi/thêm provider khác (GHTK...) chỉ
        // cần implement ShippingProviderInterface rồi đổi binding ở đây.
        $this->app->bind(ShippingProviderInterface::class, GhnProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
