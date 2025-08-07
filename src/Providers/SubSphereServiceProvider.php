<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Providers;

use AhmedEssam\SubSphere\Commands\ExpireSubscriptionsCommand;
use AhmedEssam\SubSphere\Commands\RenewSubscriptionsCommand;
use AhmedEssam\SubSphere\Commands\ResetUsageCommand;
use AhmedEssam\SubSphere\Contracts\PlanRepositoryContract;
use AhmedEssam\SubSphere\Contracts\SubscriptionRepositoryContract;
use AhmedEssam\SubSphere\Contracts\SubscriptionServiceContract;
use AhmedEssam\SubSphere\Events\SubscriptionChanged;
use AhmedEssam\SubSphere\Events\SubscriptionCreated;
use AhmedEssam\SubSphere\Listeners\SendSubscriptionChangeNotification;
use AhmedEssam\SubSphere\Listeners\SendSubscriptionCreatedNotification;
use AhmedEssam\SubSphere\Repositories\Eloquent\PlanRepository;
use AhmedEssam\SubSphere\Repositories\Eloquent\SubscriptionRepository;
use AhmedEssam\SubSphere\Services\CurrencyService;
use AhmedEssam\SubSphere\Services\SubscriptionService;
use AhmedEssam\SubSphere\Services\SubscriptionValidator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * SubSphere Service Provider
 * 
 * Registers package services, commands, and scheduled tasks with clean separation.
 */
class SubSphereServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerServices();
        $this->registerCommands();
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        $this->publishConfig();
        $this->loadMigrations();
        $this->scheduleCommands();
        $this->registerEventListeners();

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'sub-sphere');

        // Load translations
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'sub-sphere');
    }

    /**
     * Register package configuration
     */
    private function registerConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/sub-sphere.php', 'sub-sphere');
    }

    /**
     * Register service bindings
     */
    private function registerServices(): void
    {
        // Service contracts and implementations
        $this->app->singleton(SubscriptionServiceContract::class, SubscriptionService::class);
        $this->app->singleton(SubscriptionService::class); // Also register the concrete class as singleton
        $this->app->singleton(SubscriptionValidator::class);
        $this->app->singleton(CurrencyService::class);

        // Repository contracts and implementations
        $this->app->bind(SubscriptionRepositoryContract::class, SubscriptionRepository::class);
        $this->app->bind(PlanRepositoryContract::class, PlanRepository::class);
    }

    /**
     * Register console commands
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireSubscriptionsCommand::class,
                RenewSubscriptionsCommand::class,
                ResetUsageCommand::class,
            ]);
        }
    }

    /**
     * Publish configuration files
     */
    private function publishConfig(): void
    {
        if ($this->app->runningInConsole()) {

            // 🔧 Config
            $this->publishes([
                __DIR__ . '/../../config/sub-sphere.php' => config_path('sub-sphere.php'),
            ], 'sub-sphere-config');

            // 📦 Migrations
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'sub-sphere-migrations');

            // 👁️ Views
            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/sub-sphere'),
            ], 'sub-sphere-views');

            // 🌍 Translations
            $this->publishes([
                __DIR__ . '/../../resources/lang' => lang_path('vendor/sub-sphere'),
            ], 'sub-sphere-translations');
        }
    }

    /**
     * Load package migrations
     */
    private function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    /**
     * Schedule package commands
     */
    private function scheduleCommands(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Schedule subscription expiration check (hourly)
            $schedule->command('subscriptions:expire')
                ->hourly()
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Check for expired subscriptions');

            // Schedule auto-renewals (daily at 1 AM)
            $schedule->command('subscriptions:renew')
                ->dailyAt('01:00')
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Process auto-renewals');

            // Schedule monthly usage resets (first day of month at midnight)
            $schedule->command('subscriptions:reset-usage --period=monthly')
                ->monthlyOn(1, '00:00')
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Reset monthly usage counters');

            // Schedule daily usage resets (daily at midnight)
            $schedule->command('subscriptions:reset-usage --period=daily')
                ->dailyAt('00:00')
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Reset daily usage counters');
        });
    }

    /**
     * Register event listeners
     */
    private function registerEventListeners(): void
    {
        // Register core event listeners
        Event::listen(
            SubscriptionChanged::class,
            [SendSubscriptionChangeNotification::class, 'handle']
        );

        Event::listen(
            SubscriptionCreated::class,
            [SendSubscriptionCreatedNotification::class, 'handle']
        );
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            SubscriptionServiceContract::class,
            SubscriptionService::class,
            SubscriptionValidator::class,
            CurrencyService::class,
            SubscriptionRepositoryContract::class,
            PlanRepositoryContract::class,
        ];
    }
}
