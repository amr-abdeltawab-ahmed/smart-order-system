<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PayPalGateway;
use App\Payments\PaymentGatewayManager;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class, function ($app) {
            return new PaymentGatewayManager($app);
        });

        $this->app->bind(CreditCardGateway::class, function () {
            return new CreditCardGateway(
                apiKey: config('services.credit_card.api_key'),
                apiSecret: config('services.credit_card.api_secret'),
            );
        });

        $this->app->bind(PayPalGateway::class, function () {
            return new PayPalGateway(
                clientId: config('services.paypal.client_id'),
                clientSecret: config('services.paypal.client_secret'),
                mode: config('services.paypal.mode', 'sandbox'),
            );
        });
    }

    public function boot(): void
    {
        $manager = $this->app->make(PaymentGatewayManager::class);
        $manager->extend('credit_card', CreditCardGateway::class);
        $manager->extend('paypal', PayPalGateway::class);

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
    }
}
