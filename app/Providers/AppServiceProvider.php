<?php

namespace App\Providers;

use App\PaymentMethods\BankTransferPaymentMethod;
use App\PaymentMethods\PaymentMethodRegistry;
use App\PaymentMethods\PixOfflinePaymentMethod;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(fn (object $notifiable, string $token) => config('app.frontend_url') . "/password-reset/{$token}?email={$notifiable->getEmailForPasswordReset()}");

        // Register payment methods
        PaymentMethodRegistry::register(PixOfflinePaymentMethod::class);
        PaymentMethodRegistry::register(BankTransferPaymentMethod::class);
    }
}
