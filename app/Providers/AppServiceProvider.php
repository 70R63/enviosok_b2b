<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\MercadoPagoPaymentProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext());
        $this->app->bind(PaymentProvider::class, MercadoPagoPaymentProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
    }
}
//use Illuminate\Support\Facades\Schema;
