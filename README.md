# 🌐 SubSphere

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ahmedessam/sub-sphere.svg?style=flat-square)](https://packagist.org/packages/ahmedessam/sub-sphere)
[![Total Downloads](https://img.shields.io/packagist/dt/ahmedessam/sub-sphere.svg?style=flat-square)](https://packagist.org/packages/ahmedessam/sub-sphere)
[![License](https://img.shields.io/packagist/l/ahmedessam/sub-sphere.svg?style=flat-square)](https://packagist.org/packages/ahmedessam/sub-sphere)

A **headless Laravel subscription management package** that provides a complete, scalable, and modular solution for managing subscription plans, pricing, features, and usage tracking.

## ✨ Features

### 🎯 **Core Subscription Management**

- **Multi-tier Plans** - Create unlimited subscription plans with flexible pricing options
- **Trial Support** - Separate trial subscriptions or trial periods within paid plans
- **Subscription Lifecycle** - Complete handling of start, cancel, resume, expire, and renewal
- **Grace Period** - Configurable grace periods after subscription expiry
- **Auto-renewal** - Automatic subscription renewal with payment integration hooks

### 📊 **Feature Usage & Limits**

- **Usage Tracking** - Track feature consumption with intelligent limit enforcement
- **Reset Behaviors** - Daily, monthly, yearly, or never-resetting usage limits
- **Flexible Features** - Boolean flags, numeric limits, or custom feature values
- **Real-time Validation** - Instant feature availability and usage checking

### 🌍 **Multi-language & Currency**

- **Translation Support** - Full integration with Spatie Laravel Translatable
- **Multi-currency Pricing** - Support for multiple currencies with automatic fallbacks
- **Localized Content** - Translatable plan names, descriptions, and feature labels

### 🏗️ **Architecture & Design**

- **Fully Headless** - Backend-only package with no UI dependencies
- **Clean Architecture** - Models, Traits, Actions, Services, Events, Commands separation
- **Event-driven** - Comprehensive event system for extensibility
- **Database Agnostic** - Works with MySQL, PostgreSQL, SQLite, and more

### 🔧 **Developer Experience**

- **Artisan Commands** - Automated expiration, renewal, and usage reset commands
- **Laravel Scheduler** - Built-in support for background task automation
- **Comprehensive Testing** - Full test coverage for reliability

---

## 📋 Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 9.0 or higher
- **Database**: MySQL 5.7+ / PostgreSQL 9.6+ / SQLite 3.8+ / SQL Server 2017+

---

## � Installation

Install the package via Composer:

```bash
composer require ahmedessam/sub-sphere
```

### Publish Configuration & Migrations

```bash
# Publish config file
php artisan vendor:publish --tag="sub-sphere-config"

# Publish migrations
php artisan vendor:publish --tag="sub-sphere-migrations"

# Run migrations
php artisan migrate
```

### Publish Translations (Optional)

```bash
# Publish language files for customization
php artisan vendor:publish --tag="sub-sphere-translations"
```

---

## ⚙️ Configuration

The configuration file `config/sub-sphere.php` allows you to customize:

```php
return [
    // Grace period after subscription expiry (days)
    'grace_period_days' => env('SUBSPHERE_GRACE_PERIOD_DAYS', 3),

    // Default trial period (days)
    'trial_period_days' => env('SUBSPHERE_TRIAL_PERIOD_DAYS', 14),

    // Auto-renewal default setting
    'auto_renewal_default' => env('SUBSPHERE_AUTO_RENEWAL_DEFAULT', true),

    // Multi-currency settings
    'currency' => [
        'default' => env('SUBSPHERE_DEFAULT_CURRENCY', 'USD'),
        'supported_currencies' => ['USD', 'EUR', 'GBP', 'EGP'],
    ],

    // Plan change behavior
    'plan_changes' => [
        'allow_downgrades' => env('SUBSPHERE_ALLOW_DOWNGRADES', true),
        'reset_usage_on_plan_change' => env('SUBSPHERE_RESET_USAGE_ON_PLAN_CHANGE', true),
    ],
];
```

---

## 🎯 Quick Start

### 1. Add the Trait to Your User Model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use AhmedEssam\SubSphere\Traits\HasSubscriptions;

class User extends Authenticatable
{
    use HasSubscriptions;

    // ... rest of your User model
}
```

### 2. Create Subscription Plans

```php
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Enums\FeatureResetPeriod;

// Create a plan with translations
$plan = Plan::create([
    'slug' => 'premium',
    'name' => [
        'en' => 'Premium Plan',
        'ar' => 'الخطة المميزة'
    ],
    'description' => [
        'en' => 'Full access to all features',
        'ar' => 'وصول كامل لجميع الميزات'
    ],
    'is_active' => true,
    'sort_order' => 1
]);

// Add pricing options
$monthlyPricing = $plan->pricings()->create([
    'label' => ['en' => 'Monthly', 'ar' => 'شهري'],
    'duration_in_days' => 30,
    'price' => 29.99,
    'is_best_offer' => false
]);

// Add multi-currency pricing
$monthlyPricing->prices()->create([
    'currency_code' => 'USD',
    'amount' => 29.99
]);

$monthlyPricing->prices()->create([
    'currency_code' => 'EGP',
    'amount' => 1499.99
]);

// Add features with usage limits
$plan->features()->create([
    'key' => 'api_calls',
    'name' => ['en' => 'API Calls', 'ar' => 'استدعاءات API'],
    'description' => ['en' => 'Monthly API call limit'],
    'value' => '10000',  // 10k calls per month
    'reset_period' => FeatureResetPeriod::MONTHLY
]);

$plan->features()->create([
    'key' => 'storage_gb',
    'name' => ['en' => 'Storage Space'],
    'value' => '100',  // 100GB
    'reset_period' => FeatureResetPeriod::NEVER
]);
```

### 3. Subscribe Users

```php
$user = auth()->user();

// Start a 14-day trial
try {
    $subscription = $user->startTrial($planId, 14);
    echo "Trial started! Expires: " . $subscription->trial_ends_at;
} catch (CouldNotStartSubscriptionException $e) {
    echo "Error: " . $e->getMessage();
}

// Create a paid subscription
try {
    $subscription = $user->subscribe($planId, $pricingId);
    echo "Subscription created! Expires: " . $subscription->ends_at;
} catch (CouldNotStartSubscriptionException $e) {
    echo "Error: " . $e->getMessage();
}

// Paid subscription with trial period
try {
    $subscription = $user->subscribe($planId, $pricingId, 7); // 7-day trial
    echo "Subscription with trial created!";
} catch (CouldNotStartSubscriptionException $e) {
    echo "Error: " . $e->getMessage();
}
```

### 4. Check Subscription Status & Features

```php
$user = auth()->user();

// Check subscription status
if ($user->hasActiveSubscription()) {
    echo "User has active subscription";
    echo "Status: " . $user->subscriptionStatus()->value;
    echo "Days remaining: " . $user->daysRemaining();
}

// Check feature access
if ($user->hasFeature('api_calls')) {
    $limit = $user->getFeatureValue('api_calls');
    echo "API calls limit: " . $limit;

    // Consume feature usage
    if ($user->consumeFeature('api_calls', 100)) {
        echo "100 API calls consumed successfully";
    } else {
        echo "API call limit exceeded";
    }
}

// Check if user is on trial
if ($user->isOnTrial()) {
    echo "User is on trial period";
}

// Check grace period
if ($user->isInGracePeriod()) {
    echo "User is in grace period after subscription expiry";
}
```

### 5. Subscription Management

```php
$user = auth()->user();

// Cancel subscription (keeps access until period ends)
if ($user->cancelSubscription()) {
    echo "Subscription canceled successfully";
}

// Resume canceled subscription
if ($user->resumeSubscription()) {
    echo "Subscription resumed successfully";
}

// Change/upgrade plan
if ($user->changePlan($newPlanId)) {
    echo "Plan changed successfully";
}
```

---

## 📋 Artisan Commands

SubSphere includes several Artisan commands for automated subscription management:

### Expire Subscriptions

```bash
# Expire subscriptions past their grace period
php artisan subscriptions:expire

# Dry run (see what would be expired)
php artisan subscriptions:expire --dry-run

# Limit processing
php artisan subscriptions:expire --limit=100
```

### Auto-Renew Subscriptions

```bash
# Process subscription renewals
php artisan subscriptions:renew

# Dry run
php artisan subscriptions:renew --dry-run
```

### Reset Feature Usage

```bash
# Reset daily usage counters
php artisan subscriptions:reset-usage daily

# Reset monthly usage counters
php artisan subscriptions:reset-usage monthly

# Reset yearly usage counters
php artisan subscriptions:reset-usage yearly

# Dry run
php artisan subscriptions:reset-usage monthly --dry-run
```

### Schedule Commands

Add to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Check for expired subscriptions every hour
    $schedule->command('subscriptions:expire')
             ->hourly()
             ->withoutOverlapping();

    // Reset daily usage at midnight
    $schedule->command('subscriptions:reset-usage daily')
             ->dailyAt('00:00')
             ->withoutOverlapping();

    // Reset monthly usage on first day of month
    $schedule->command('subscriptions:reset-usage monthly')
             ->monthlyOn(1, '00:00')
             ->withoutOverlapping();

    // Auto-renew subscriptions daily
    $schedule->command('subscriptions:renew')
             ->dailyAt('01:00')
             ->withoutOverlapping();
}
```

---

## 🔔 Events

SubSphere dispatches events throughout the subscription lifecycle:

```php
// Subscription Events
AhmedEssam\SubSphere\Events\SubscriptionStarted::class
AhmedEssam\SubSphere\Events\TrialStarted::class
AhmedEssam\SubSphere\Events\SubscriptionChanged::class
AhmedEssam\SubSphere\Events\SubscriptionCanceled::class
AhmedEssam\SubSphere\Events\SubscriptionRenewed::class
AhmedEssam\SubSphere\Events\SubscriptionExpired::class

// Feature Events
AhmedEssam\SubSphere\Events\FeatureUsed::class
AhmedEssam\SubSphere\Events\FeatureUsageReset::class
```

### Listening to Events

```php
// In your EventServiceProvider.php
protected $listen = [
    \AhmedEssam\SubSphere\Events\SubscriptionStarted::class => [
        \App\Listeners\SendWelcomeEmail::class,
        \App\Listeners\GrantAccessToResources::class,
    ],
    \AhmedEssam\SubSphere\Events\SubscriptionExpired::class => [
        \App\Listeners\SendExpirationNotice::class,
        \App\Listeners\RevokeAccess::class,
    ],
];
```

---

## 🧪 Testing

Run the package tests:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test suite
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Integration
```

---

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/aahmedessam30/sub-sphere.git
cd sub-sphere

# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer format
```

### Reporting Issues

Please report issues on [GitHub Issues](https://github.com/aahmedessam30/sub-sphere/issues).

---

## 🔐 Security

If you discover any security-related issues, please email [aahmedessam30@gmail.com](mailto:aahmedessam30@gmail.com) instead of using the issue tracker.

---

## 📄 License

SubSphere is open-sourced software licensed under the [MIT license](LICENSE.md).

---

## 👨‍💻 Author

**Ahmed Essam**

- Email: [aahmedessam30@gmail.com](mailto:aahmedessam30@gmail.com)
- GitHub: [@aahmedessam30](https://github.com/aahmedessam30)

---

## ⭐ Support

If this package helped you, please consider:

- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features

---

<div align="center">

**Made with ❤️ for the Laravel community**

[⬆ Back to top](#-subsphere)

</div>
