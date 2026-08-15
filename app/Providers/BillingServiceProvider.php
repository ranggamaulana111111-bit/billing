<?php

namespace App\Providers;

use App\Services\Billing\BillingService;
use App\Services\Billing\CustomerCodeGenerator;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Payment\MidtransGateway;
use App\Services\Payment\PaymentService;
use App\Services\Payment\XenditGateway;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CustomerCodeGenerator::class);
        $this->app->singleton(InvoiceGenerator::class);

        $this->app->singleton(PaymentService::class, function ($app) {
            $service = new PaymentService;
            $service->registerGateway('midtrans', new MidtransGateway);
            $service->registerGateway('xendit', new XenditGateway);

            return $service;
        });

        $this->app->singleton(BillingService::class, function ($app) {
            return new BillingService(
                $app->make(CustomerCodeGenerator::class),
                $app->make(InvoiceGenerator::class),
                $app->make(PaymentService::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
