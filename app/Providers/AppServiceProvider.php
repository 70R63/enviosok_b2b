<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\MercadoPagoPaymentProvider;
use App\Domain\Network\Commerce\Contracts\PlatformPaymentProvider;
use App\Domain\Network\Commerce\MercadoPagoPlatformPaymentProvider;
use App\Domain\Shipping\Local\Routing\{GoogleRoutesDistanceProvider,RouteDistanceProvider};
use App\Domain\AI\Actions\ActionRegistry;
use App\Domain\AI\Channels\WhatsApp\Contracts\WhatsAppProvider;
use App\Infrastructure\WhatsApp\MetaCloudWhatsAppProvider;

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
        $this->app->bind(PlatformPaymentProvider::class, MercadoPagoPlatformPaymentProvider::class);
        $this->app->bind(RouteDistanceProvider::class, GoogleRoutesDistanceProvider::class);
        $this->app->singleton(ActionRegistry::class, fn ($app) => new ActionRegistry($app->make(\App\Domain\Network\Billing\EntitlementService::class)));
        $this->app->bind(WhatsAppProvider::class, MetaCloudWhatsAppProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        app(\App\Services\Security\StageSafetyGuard::class)->enforce();
        \App\Domain\Shipping\Local\Models\LocalTrackingEvent::created(function ($event): void { try { app(\App\Domain\ApiHub\ApiWebhookPublisher::class)->tracking($event); } catch (\Throwable $exception) { \Illuminate\Support\Facades\Log::warning('api_hub.webhook_queue_failed',['tracking_event_id'=>$event->id]); } });
        Schema::defaultStringLength(191);
        app(\App\Domain\Shipping\AI\Actions\ZigoLogisticsActionRegistrar::class)->register();
    }
}
//use Illuminate\Support\Facades\Schema;
