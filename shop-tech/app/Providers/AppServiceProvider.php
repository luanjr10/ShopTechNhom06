<?php

namespace App\Providers;

use App\Models\Product;
use App\Observers\ProductPriceObserver;
use App\Services\Shipping\GhnProvider;
use App\Services\Shipping\ShippingProviderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShippingProviderInterface::class, GhnProvider::class);
    }

    public function boot(): void
    {
        Product::observe(ProductPriceObserver::class);
    }
}
