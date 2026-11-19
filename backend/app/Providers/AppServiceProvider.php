<?php

namespace App\Providers;

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
        ini_set('memory_limit', '-1');

        // Activity log: who changed what in the admin panel
        $audited = [
            \App\Models\Order::class, \App\Models\MenuItem::class, \App\Models\Category::class,
            \App\Models\Offer::class, \App\Models\Coupon::class, \App\Models\FeaturedCard::class,
            \App\Models\MarketingCard::class, \App\Models\ReceiptSetting::class, \App\Models\DeliveryFeeSetting::class,
            \App\Models\User::class, \App\Models\Review::class, \App\Models\LoyaltyTransaction::class,
        ];
        foreach ($audited as $model) {
            if (class_exists($model)) {
                $model::observe(\App\Observers\AuditObserver::class);
            }
        }

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            if ($event->user instanceof \App\Models\User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                \App\Models\ActivityLog::record('login', "{$event->user->name} logged in", null, [], $event->user);
            }
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) {
            $email = $event->credentials['email'] ?? 'unknown';
            \App\Models\ActivityLog::record('login_failed', "Failed admin login attempt for {$email}", null, [], null);
        });
    }
}
