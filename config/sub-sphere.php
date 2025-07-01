<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Subscription Grace Period
    |--------------------------------------------------------------------------
    |
    | This value defines the default grace period (in days) that subscribers
    | get after their subscription expires before losing access completely.
    |
    */
    'grace_period_days' => env('SUBSPHERE_GRACE_PERIOD_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Default Trial Period
    |--------------------------------------------------------------------------
    |
    | The default trial period (in days) for new subscriptions when no
    | specific trial period is specified.
    |
    */
    'trial_period_days' => env('SUBSPHERE_TRIAL_PERIOD_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Auto-Renewal Default
    |--------------------------------------------------------------------------
    |
    | Whether subscriptions should auto-renew by default when created.
    |
    */
    'auto_renewal_default' => env('SUBSPHERE_AUTO_RENEWAL_DEFAULT', true),

    /*
    |--------------------------------------------------------------------------
    | Usage Reset Schedule
    |--------------------------------------------------------------------------
    |
    | Define when the automatic usage reset command should run.
    | This affects the scheduling of feature usage resets.
    |
    */
    'usage_reset_schedule' => [
        'daily' => '0 0 * * *',    // Every day at midnight
        'monthly' => '0 0 1 * *',  // First day of month at midnight
        'yearly' => '0 0 1 1 *',   // First day of year at midnight
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Expiry Check Schedule
    |--------------------------------------------------------------------------
    |
    | How often to check for expired subscriptions.
    |
    */
    'expiry_check_schedule' => '0 * * * *', // Every hour

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package.
    |
    */
    'tables' => [
        'plans' => 'plans',
        'plan_pricings' => 'plan_pricings',
        'plan_prices' => 'plan_prices',
        'plan_features' => 'plan_features',
        'subscriptions' => 'subscriptions',
        'subscription_usages' => 'subscription_usages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for trial period restrictions and duration limits.
    |
    */
    'trial' => [
        'allow_multiple_trials_per_plan' => env('SUBSPHERE_ALLOW_MULTIPLE_TRIALS_PER_PLAN', false),
        'min_days' => env('SUBSPHERE_TRIAL_MIN_DAYS', 3),
        'max_days' => env('SUBSPHERE_TRIAL_MAX_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Currency Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for multi-currency pricing support.
    |
    */
    'currency' => [
        'default' => env('SUBSPHERE_DEFAULT_CURRENCY', 'EGP'),
        'fallback_to_default' => env('SUBSPHERE_FALLBACK_TO_DEFAULT_CURRENCY', true),
        'supported_currencies' => [
            'EGP',
            'USD',
            'EUR',
            'GBP',
            'CAD',
            'AUD',
            'JPY',
            'CHF',
            'SEK',
            'NOK',
            'DKK'
        ],
        'currency_symbols' => [
            'EGP' => 'E£',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'JPY' => '¥',
            'CHF' => 'CHF',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Change Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for subscription plan upgrades and downgrades.
    |
    */
    'plan_changes' => [
        'allow_downgrades' => env('SUBSPHERE_ALLOW_DOWNGRADES', true),
        'prevent_downgrade_with_excess_usage' => env('SUBSPHERE_PREVENT_DOWNGRADE_WITH_EXCESS_USAGE', true),
        'allow_plan_change_during_trial' => env('SUBSPHERE_ALLOW_PLAN_CHANGE_DURING_TRIAL', true),
        'reset_usage_on_plan_change' => env('SUBSPHERE_RESET_USAGE_ON_PLAN_CHANGE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for command logging and progress tracking.
    |
    */
    'logging' => [
        'log_command_progress' => env('SUBSPHERE_LOG_COMMAND_PROGRESS', false),
        'log_command_results' => env('SUBSPHERE_LOG_COMMAND_RESULTS', true),
    ],
];
