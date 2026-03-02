<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\Settlement;
use App\Observers\ProductObserver;
use App\Observers\SettlementObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        Settlement::observe(SettlementObserver::class);
    }
}
