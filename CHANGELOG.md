# Changelog

All notable changes to `sub-sphere` will be documented in this file.

## [v1.5.1] - 2025-08-08

### 🔧 **Critical Migration Hotfix**

#### 🚨 **Bug Fixes**

- **Migration Index Conflict** - Fixed duplicate key error in `plan_features_key_reset_period_index` during enum-to-string migration
- **Index Management** - Updated migration to properly drop existing indexes before column modifications
- **Rollback Safety** - Enhanced down() method with proper index handling for safe rollbacks
- **Production Compatibility** - Ensured migration works reliably across all MySQL configurations

#### 🛠️ **Technical Improvements**

- **Migration Sequencing** - Proper order of index drops and recreations
- **Error Prevention** - Systematic approach to avoid duplicate index conflicts
- **Data Integrity** - Maintained zero data loss during corrected migration process
- **Enhanced Testing** - Added verification steps for successful migration completion

#### 📋 **Files Updated**

- **`database/migrations/2025_08_07_000001_convert_plan_features_reset_period_to_string.php`** - Fixed index conflict resolution
- **Documentation** - Updated troubleshooting guide with specific fix procedures

---

## [v1.5.0] - 2025-08-07

### 🔄 **Database Architecture Enhancement**

#### 🚀 **Major Features**

- **Database Enum Migration** - Converted database enums to PHP enums with string columns for better flexibility
- **Migration Scripts** - Added comprehensive migration files with full rollback support
- **Type Safety Maintained** - PHP enum casting preserves type safety while improving database flexibility
- **Cross-Database Compatibility** - String columns work universally across MySQL, PostgreSQL, SQLite
- **Zero Downtime Extensibility** - Add new enum values without database schema changes

#### 🏗️ **Database Schema Changes**

- **`plan_features.reset_period`** - Converted from ENUM to VARCHAR(20) with preserved data
- **`subscriptions.status`** - Converted from ENUM to VARCHAR(20) with index management
- **Index Preservation** - All composite indexes properly maintained during migration
- **Data Integrity** - Safe migration with temporary columns and complete rollback support

#### 🔧 **Technical Improvements**

- **Enhanced IDE Support** - Better autocomplete and static analysis for enum usage
- **Easier Testing** - Simplified mocking and validation testing of enum values  
- **Future Extensibility** - Add new enum cases without database migrations
- **Performance Maintained** - String columns with proper indexing maintain query performance
- **Deployment Safety** - Complete up/down migrations for safe production deployment

#### 💻 **Developer Experience**

- **Backward Compatible** - No breaking changes, existing code works unchanged
- **Type-Safe Operations** - PHP enums provide compile-time validation
- **Enhanced Methods** - Enum methods like `label()`, `color()` continue working
- **Documentation Updated** - Comprehensive migration guide and best practices

---

## [v1.4.0] - 2025-08-07

### 🌍 **Complete Translation System Overhaul**

#### 🔥 **Major Features**

- **Enum Translation Support** - Added full translation support for `SubscriptionStatus` and `FeatureResetPeriod` enums
- **Bilingual Support** - Complete English and Arabic translations with proper RTL support
- **Translation Namespace Consistency** - Fixed 110+ translation calls to use proper `sub-sphere::` namespace prefix
- **Email Template Updates** - Updated all email templates with consistent translation namespace
- **Translation Publishing** - Users can now publish and customize all package translations
- **Graceful Fallbacks** - Robust fallback mechanisms when translations are unavailable

#### 🎯 **Translation Keys Added**

- **Status Labels**: `pending`, `trial`, `active`, `inactive`, `canceled`, `expired`
- **Reset Period Labels**: `never`, `daily`, `monthly`, `yearly`
- **Complete Arabic Translations**: Full RTL language support
- **Extensible Structure**: Easy to add more languages

#### 🔧 **Technical Improvements**

- **100% Translation Coverage** - Every translation call uses correct namespace
- **Laravel Standards Compliance** - All translations follow Laravel package best practices
- **User Customization Support** - Automatic resolution of customized vs. default translations
- **Performance Optimized** - Minimal overhead with efficient fallback mechanisms

#### 📧 **Files Updated**

- All enum files with translation support and fallbacks
- All email template files (5 templates, 110+ translation calls)
- English and Arabic translation files
- Listener files with corrected translation namespace

#### 🚀 **Usage Examples**

```php
// Get localized labels
SubscriptionStatus::ACTIVE->label(); // "Active" or "نشط"
FeatureResetPeriod::MONTHLY->label(); // "Monthly" or "شهرياً"

// Publish for customization
php artisan vendor:publish --tag=sub-sphere-translations
```

## [v1.3.1] - 2025-01-08

### 🐛 **Critical Bug Fix Release**

#### 🔧 **Complete Property Access Fixes**

- **Fixed All Property Access Issues** - Corrected all instances of `$subscription->pricing` to `$subscription->planPricing` throughout the codebase:
  - **HasSubscriptions Trait** - Fixed pricing access in subscription management methods
  - **RenewSubscriptionsCommand.php** - Fixed pricing availability check during renewal
  - **ChangeSubscriptionPlanAction.php** - Fixed all pricing references in plan change operations
  - **Additional Files** - Comprehensive fix across all affected files
- **Resolved Undefined Property Errors** - Eliminated errors caused by accessing non-existent `pricing` property
- **Enhanced Reliability** - Ensures complete elimination of undefined property errors
- **Improved Command Stability** - Renewal command now properly accesses plan pricing
- **Better Plan Change Logic** - Plan change action correctly references pricing relationships
- **Improved Code Consistency** - Ensures all subscription pricing access uses the correct `planPricing` relationship

#### 🎯 **Impact**

- **Complete Error Resolution** - All undefined property issues are now resolved
- **Command Stability** - Renewal and plan change operations work reliably
- **Consistent API** - All subscription pricing access follows correct patterns
- **Error Prevention** - Eliminates undefined property exceptions
- **Code Reliability** - Ensures consistent property access patterns
- **Better Performance** - Removes potential null reference issues
- **Production Stability** - Critical fix for applications using subscription pricing features

### 🔄 **Internal Changes**

- **Property Name Standardization** - Unified all pricing property access to use `planPricing`
- **Code Quality** - Improved consistency across the codebase
- **Error Handling** - Better error prevention through correct property access

---

## [v1.3.0] - 2025-07-06

### 🔍 **Comprehensive Subscription Status Management**

#### ✨ **New Feature: Complete Status Checking Suite**

- **Enhanced `HasSubscriptions` Trait** - Added comprehensive subscription status checking functionality
- **Complete Coverage** - Check for all available subscription statuses
- **Analytics Support** - Get subscription counts and historical status data
- **Business Intelligence** - Perfect for customer segmentation and retention strategies

#### 🔧 **Technical Improvements**

##### New Status Checking Methods

- **`hasCancelledSubscription()`** - Check for cancelled subscriptions
- **`hasPendingSubscription()`** - Check for pending subscriptions
- **`hasTrialSubscription()`** - Check for trial subscriptions
- **`hasInactiveSubscription()`** - Check for inactive subscriptions
- **`hasExpiredSubscription()`** - Check for expired subscriptions
- **`hasInactiveSubscriptions()`** - Check for any inactive status (pending, inactive, canceled, expired)

##### Analytics & Utility Methods

- **`getSubscriptionCountByStatus(SubscriptionStatus $status)`** - Get count of subscriptions by specific status
- **`getSubscriptionStatuses()`** - Get all statuses the subscriber has ever had

```php
// Individual status checks
if ($user->hasPendingSubscription()) {
    $this->sendPaymentReminder($user);
}

if ($user->hasExpiredSubscription() && !$user->hasActiveSubscription()) {
    $this->offerRenewalDiscount($user);
}

// Analytics and counting
$canceledCount = $user->getSubscriptionCountByStatus(SubscriptionStatus::CANCELED);
$allStatuses = $user->getSubscriptionStatuses(); // ['trial', 'active', 'canceled']
```

#### 🎯 **Use Cases**

- **Customer Segmentation** - Identify users by subscription history patterns
- **Marketing Campaigns** - Target users with specific subscription behaviors
- **Retention Strategies** - Identify churn patterns and recovery opportunities
- **Analytics Dashboard** - Track subscription lifecycle and user journeys
- **Customer Support** - Quickly understand customer's subscription history
- **Business Intelligence** - Comprehensive subscription status analytics

#### 🏗️ **Code Quality Improvements**

- **Efficient Queries** - All methods use `exists()` for optimal performance
- **Type Safety** - Uses proper enum types throughout
- **Consistent API** - Follows established naming conventions
- **Historical Analysis** - Track all statuses a user has experienced
- **Flexible Counting** - Get specific counts when needed

### 🔄 **Internal Changes**

- **Method Organization** - Logical grouping of all status checking methods
- **Performance Optimization** - Efficient database queries for status checks
- **API Consistency** - All methods follow consistent patterns and naming

---

## [v1.2.0] - 2025-07-06

### 🔄 **Enhanced Subscription Management**

#### ✨ **New Feature: Subscription Renewal**

- **Enhanced `HasSubscriptions` Trait** - Added comprehensive subscription renewal functionality
- **Smart Renewal Logic** - Automatic validation of renewal eligibility and subscription state
- **Robust Error Handling** - Comprehensive logging and graceful failure handling
- **Action Pattern Integration** - Uses existing `RenewSubscriptionAction` for consistency

#### 🔧 **Technical Improvements**

##### New Method

- **`renewSubscription()`** - Renew current active subscription with validation
  ```php
  // Simple renewal attempt
  if ($user->renewSubscription()) {
      echo "Subscription renewed successfully!";
  } else {
      echo "Unable to renew - may need new subscription";
  }
  ```

##### Key Features

- **Active Subscription Validation** - Only allows renewal of active/grace period subscriptions
- **Renewal Eligibility Check** - Uses `canRenew()` method for business logic validation
- **Manual Renewal Flag** - Explicitly marks renewals as manual (not auto-renewal)
- **Comprehensive Logging** - Detailed error logging with subscriber and subscription context
- **Boolean Return** - Clean `true`/`false` return for easy conditional logic

##### Business Logic

- **Requires Active Subscription** - Must have subscription in active status or grace period
- **Grace Period Support** - Can renew subscriptions still within grace period
- **Expired Subscription Handling** - Returns `false` for completely expired subscriptions
- **Fallback Pattern** - Perfect for "renew or subscribe" service patterns

#### 🎯 **Use Cases**

- **Manual Subscription Renewal** - User-initiated renewal through UI
- **Renewal vs New Subscription** - Service layer can attempt renewal first, then new subscription
- **Grace Period Extensions** - Allow renewals during grace periods
- **Subscription Recovery** - Help users maintain continuous service

#### 🏗️ **Code Quality Improvements**

- **Consistent Code Style** - Improved array formatting and alignment throughout trait
- **Enhanced Documentation** - Clear method documentation with business logic explanation
- **Error Context** - Rich error logging with subscriber type, ID, and subscription details
- **Pattern Consistency** - Follows same patterns as `cancelSubscription()` and `resumeSubscription()`

### 🔄 **Internal Changes**

- **Code Formatting** - Improved array alignment and consistency in `HasSubscriptions` trait
- **Import Addition** - Added `RenewSubscriptionAction` import for renewal functionality
- **Method Organization** - Logical grouping of subscription management methods

---

## [v1.1.0] - 2025-07-03

### 🌍 **New Feature: Translatable Flexible Values**

#### ✨ **Major Enhancement**

- **Translatable Plan Features** - Plan feature values can now be different per locale
- **Smart Fallback System** - Automatic fallback to default locale when translation missing
- **Full Backward Compatibility** - Existing non-translatable features continue to work unchanged
- **Unified API** - Single trait handles both translatable and non-translatable values seamlessly

#### 🔧 **Technical Improvements**

##### New Components

- **`TranslatableFlexibleValueCast`** - New cast for handling translatable flexible values
- **Enhanced `HandlesFlexibleValues` trait** - Unified trait with comprehensive flexible value operations
- **Locale-aware methods** - All flexible value methods now support optional locale parameter

##### Core Features

- **Multi-locale Value Storage** - Store different feature values for different languages
  ```php
  $feature->value = [
      'en' => 1000,
      'ar' => 2000,
      'fr' => 1500
  ];
  ```
- **Locale-aware Access** - Get values for specific locales with automatic fallback
  ```php
  $feature->getLocalizedValue('ar');     // 2000
  $feature->getLocalizedValue('de');     // Falls back to 'en': 1000
  ```
- **Translation Management** - Full CRUD operations for translatable values
  ```php
  $feature->setValueForLocale('es', 1200);
  $feature->getValueLocales();           // ['en', 'ar', 'fr', 'es']
  $feature->hasValueTranslation('es');   // true
  ```

##### Enhanced API (30+ Methods)

- **Type Detection** - `isNumericFeature('ar')`, `isBooleanFeature('fr')`
- **Value Access** - `getNumericValue('en')`, `getBooleanValue('ar')`
- **Validation** - `isEnabled('fr')`, `isUnlimited('en')`
- **Comparison** - `compareValue(1200, 'ar')`, `isBetterThan(500, 'en')`
- **Display** - `getDisplayValue('ar')`, `getLocalizedDisplayValue('fr')`

#### 🧪 **Testing & Quality**

- **Comprehensive Test Suite** - 27 new tests covering all translatable scenarios
- **100% Backward Compatibility** - All existing tests pass without modification
- **Edge Case Coverage** - Null values, single values, mixed scenarios
- **Locale Fallback Testing** - Complete fallback behavior validation

#### 📚 **Documentation**

- **Complete Documentation** - New `docs/TRANSLATABLE_VALUES.md` guide
- **Usage Examples** - Real-world examples for all features
- **Migration Guide** - How to upgrade existing features to translatable
- **Best Practices** - Recommended patterns and approaches

#### 🏗️ **Architecture Improvements**

- **Code Consolidation** - Merged duplicate functionality into unified trait
- **Zero Duplication** - Eliminated all redundant code between traits
- **Performance Optimization** - Single trait lookup instead of multiple
- **Cleaner Codebase** - Reduced from 2 traits to 1 comprehensive trait

#### 🎯 **Use Cases**

- **Multi-language SaaS** - Different plan limits per market/locale
- **Regional Pricing** - Feature values that vary by region
- **Localized Features** - Features with culture-specific configurations
- **A/B Testing** - Different feature limits for different user segments

### 🔄 **Internal Changes**

- **Trait Unification** - `HandlesFlexibleValues` and `HandlesTranslatableFlexibleValues` merged
- **Method Consistency** - All methods follow consistent parameter patterns
- **Type Safety** - Enhanced type hints and return types throughout
- **Code Quality** - Improved documentation and inline comments

---

## [v1.0.0] - 2025-07-01

### 🎉 Initial Release

This is the first stable release of SubSphere - a comprehensive, headless Laravel subscription management package.

### ✨ Features

#### 🏗️ **Core Architecture**

- **Clean Architecture** - Separation of concerns with Models, Traits, Actions, Services, Events, and Commands
- **Headless Design** - Backend-only package with no UI dependencies
- **Database Agnostic** - Support for MySQL, PostgreSQL, SQLite, and SQL Server
- **Laravel Integration** - Full Laravel 9+ and PHP 8.1+ support

#### 📋 **Subscription Management**

- **Multi-tier Plans** - Create unlimited subscription plans with flexible configuration
- **Flexible Pricing** - Multiple pricing options per plan with different durations
- **Multi-currency Support** - Handle multiple currencies with automatic fallbacks
- **Trial Subscriptions** - Separate trial subscriptions or trial periods within paid plans
- **Grace Period** - Configurable grace periods after subscription expiry
- **Auto-renewal** - Automatic subscription renewal with payment integration hooks

#### 📊 **Feature Usage System**

- **Usage Tracking** - Track feature consumption with intelligent limit enforcement
- **Reset Behaviors** - Daily, monthly, yearly, or never-resetting usage limits
- **Flexible Features** - Boolean flags, numeric limits, or custom feature values
- **Real-time Validation** - Instant feature availability and usage checking
- **Usage Analytics** - Comprehensive usage reporting and analytics

#### 🌍 **Internationalization**

- **Spatie Translatable** - Full integration with Spatie Laravel Translatable
- **Multi-language Support** - Translatable plan names, descriptions, and feature labels
- **RTL Support** - Right-to-left language support for Arabic and other RTL languages
- **Localized Content** - Email templates and notifications in multiple languages

#### 🔔 **Event-Driven Architecture**

- **Comprehensive Events** - Events for subscription lifecycle, feature usage, and billing
- **Extensibility** - Easy integration with external services and custom logic
- **Event Listeners** - Built-in listeners for common subscription workflows

#### ⚡ **Automation & Commands**

- **Artisan Commands** - Automated expiration, renewal, and usage reset commands
- **Laravel Scheduler** - Built-in support for background task automation
- **Batch Processing** - Efficient batch processing for large-scale operations
- **Dry Run Mode** - Test commands without making actual changes

#### 🎨 **Optional Integrations**

- **Filament v3** - Optional admin panel integration for easy management
- **Payment Gateways** - Hooks for integrating with Stripe, PayPal, and other providers
- **Email System** - Built-in email notifications with customizable templates

### 📦 **Package Components**

#### Models

- `Plan` - Subscription plans with translatable content
- `PlanPricing` - Pricing options for plans
- `PlanPrice` - Multi-currency pricing support
- `PlanFeature` - Feature definitions with usage limits
- `Subscription` - User subscriptions with lifecycle management
- `SubscriptionUsage` - Feature usage tracking

#### Traits

- `HasSubscriptions` - Add subscription capabilities to any model
- `HasTranslatableHelpers` - Helper methods for translatable content
- `SubscriptionBehaviors` - Subscription state management
- `FeatureUsageTracking` - Feature consumption tracking

#### Actions

- `CreateSubscriptionAction` - Handle paid subscription creation
- `StartTrialAction` - Handle trial subscription creation
- `CancelSubscriptionAction` - Handle subscription cancellation
- `ResumeSubscriptionAction` - Handle subscription resumption
- `ChangeSubscriptionPlanAction` - Handle plan upgrades/downgrades
- `ConsumeFeatureAction` - Handle feature usage consumption
- `ExpireSubscriptionAction` - Handle subscription expiration
- `RenewSubscriptionAction` - Handle subscription renewal

#### Services

- `SubscriptionService` - Main orchestration service
- `SubscriptionValidator` - Validation logic

#### Events

- `SubscriptionStarted` - When a paid subscription begins
- `TrialStarted` - When a trial subscription begins
- `SubscriptionCreated` - Generic subscription creation event
- `SubscriptionChanged` - When subscription plan changes
- `SubscriptionCanceled` - When subscription is canceled
- `SubscriptionRenewed` - When subscription is renewed
- `SubscriptionExpired` - When subscription expires
- `FeatureUsed` - When a feature is consumed
- `FeatureUsageReset` - When usage counters are reset

#### Commands

- `ExpireSubscriptionsCommand` - Expire old subscriptions
- `RenewSubscriptionsCommand` - Process subscription renewals
- `ResetUsageCommand` - Reset feature usage counters

#### Enums

- `SubscriptionStatus` - Subscription state enumeration
- `FeatureResetPeriod` - Usage reset period options

### 🔧 **Configuration Options**

- Grace period customization
- Trial period limits and defaults
- Auto-renewal behavior
- Multi-currency settings
- Plan change restrictions
- Usage reset schedules
- Supported locales and fallbacks

### 🧪 **Testing**

- **Complete Test Suite** - Comprehensive unit and integration tests
- **Feature Tests** - Real-world scenario testing
- **Database Testing** - Multi-database compatibility testing
- **Event Testing** - Event dispatching and handling verification

### 📚 **Documentation**

- **README.md** - Comprehensive installation and usage guide
- **PACKAGE_GUIDE.md** - Detailed implementation guide with examples
- **API Documentation** - Complete method and class documentation
- **Code Examples** - Real-world usage scenarios

### 🛡️ **Security**

- **Input Validation** - Comprehensive validation of all inputs
- **SQL Injection Protection** - Secure database queries
- **Authorization** - Built-in permission checking
- **Data Sanitization** - Proper data handling and sanitization

### 🚀 **Performance**

- **Database Optimization** - Efficient queries and indexing
- **Caching Support** - Laravel cache integration
- **Batch Operations** - Efficient bulk processing
- **Memory Management** - Optimized for large datasets

### 🔄 **Migration Path**

- **Clean Migrations** - Well-structured database migrations
- **Rollback Support** - Safe migration rollbacks
- **Data Integrity** - Foreign key constraints and data validation

### 📋 **Requirements**

- PHP 8.1 or higher
- Laravel 9.0 or higher
- MySQL 5.7+ / PostgreSQL 9.6+ / SQLite 3.8+ / SQL Server 2017+

### 🏷️ **Dependencies**

- `spatie/laravel-translatable`: ^6.0
- `nesbot/carbon`: ^2.0|^3.0
- `illuminate/database`: ^10.0|^11.0
- `illuminate/events`: ^10.0|^11.0
- `illuminate/support`: ^10.0|^11.0

### 👨‍💻 **Development**

- **PSR-12** - Follows PSR-12 coding standards
- **PHPStan** - Static analysis integration
- **Laravel Pint** - Code formatting
- **CI/CD Ready** - GitHub Actions workflow included

### 🤝 **Community**

- **Open Source** - MIT license for maximum flexibility
- **Community Driven** - Open to contributions and feedback
- **Documentation** - Comprehensive guides and examples
- **Support** - Active issue tracking and support

---

## Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review our [Security Policy](SECURITY.md) on how to report security vulnerabilities.

## License

SubSphere is open-sourced software licensed under the [MIT license](LICENSE.md).

## Credits

- **Ahmed Essam** - Creator and maintainer
- **Laravel Community** - For the amazing framework
- **Spatie** - For the excellent Laravel Translatable package

---

**Built with ❤️ for the Laravel community**
