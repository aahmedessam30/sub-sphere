<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('sub-sphere::subscription.subjects.subscription_upgraded') }}</title>
    <style>
        body {
            font-family: {{ app()->getLocale() === 'ar' ? "'Segoe UI', 'Tahoma', 'Cairo', sans-serif" : "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" }};
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }};
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }

        .content {
            padding: 40px 30px;
        }

        .upgrade-highlight {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }

        .plan-comparison {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 20px;
            align-items: center;
            margin: 20px 0;
        }

        .plan-box {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .plan-box.old {
            opacity: 0.6;
        }

        .plan-box.new {
            border-color: #48bb78;
            box-shadow: 0 0 0 2px rgba(72, 187, 120, 0.1);
        }

        .plan-name {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .plan-price {
            font-size: 16px;
            color: #48bb78;
            font-weight: 500;
        }

        .arrow {
            font-size: 24px;
            color: #48bb78;
        }

        .features-list {
            background-color: #f0fff4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #48bb78;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }

        .feature-icon {
            color: #48bb78;
            margin-right: 10px;
            font-weight: bold;
        }

        .effective-date {
            background-color: #edf2f7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }

        .footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .footer p {
            margin: 0;
            color: #718096;
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .plan-comparison {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .arrow {
                transform: rotate(90deg);
            }

            .content {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🚀 {{ __('sub-sphere::subscription.headers.upgraded') }}</h1>
            <p>{{ __('sub-sphere::subscription.content.upgraded.subtitle', ['plan_name' => $newPlan->name]) }}</p>
        </div>

        <div class="content">
            <p>{{ __('sub-sphere::subscription.content.greeting') }}</p>

            <p>{!! __('sub-sphere::subscription.content.upgraded.congratulations', ['plan_name' => $newPlan->name]) !!}</p>

            <div class="upgrade-highlight">
                <h2 style="margin: 0 0 10px 0;">🎉
                    {{ __('sub-sphere::subscription.content.upgraded.welcome_box_title', ['plan_name' => $newPlan->name]) }}</h2>
                <p style="margin: 0;">{{ __('sub-sphere::subscription.content.upgraded.welcome_box_subtitle') }}</p>
            </div>

            <div class="plan-comparison">
                <div class="plan-box old">
                    <div class="plan-name">{{ $oldPlan->name }}</div>
                    <div class="plan-price">
                        {{ number_format($oldPlan->price, 2) }} {{ strtoupper($oldPlan->currency) }}
                        @if ($oldPlan->billing_interval)
                            / {{ $oldPlan->billing_interval }}
                        @endif
                    </div>
                </div>

                <div class="arrow">⬆️</div>

                <div class="plan-box new">
                    <div class="plan-name">{{ $newPlan->name }}</div>
                    <div class="plan-price">
                        {{ number_format($newPlan->price, 2) }} {{ strtoupper($newPlan->currency) }}
                        @if ($newPlan->billing_interval)
                            / {{ $newPlan->billing_interval }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="effective-date">
                <strong>{{ __('sub-sphere::subscription.content.upgrade_effective') }}</strong>
                {{ $effectiveDate->format('M j, Y \a\t g:i A') }}
            </div>

            @if ($newPlan->description)
                <div class="features-list">
                    <h3 style="margin-top: 0; color: #2d3748;">{{ __('sub-sphere::subscription.content.plan_includes') }}</h3>
                    <p>{{ is_array($newPlan->description) ? $newPlan->description[app()->getLocale()] ?? ($newPlan->description['en'] ?? '') : $newPlan->description }}
                    </p>
                </div>
            @endif

            <div class="features-list">
                <h3 style="margin-top: 0; color: #2d3748;">{{ __('sub-sphere::subscription.content.upgraded.features_title') }}
                </h3>
                <div class="feature-item">
                    <span class="feature-icon">✓</span>
                    <span>{{ __('sub-sphere::subscription.content.upgraded.immediate_access') }}</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✓</span>
                    <span>{{ __('sub-sphere::subscription.content.upgraded.enhanced_functionality') }}</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✓</span>
                    <span>{{ __('sub-sphere::subscription.content.upgraded.priority_support') }}</span>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">✓</span>
                    <span>{{ __('sub-sphere::subscription.content.upgraded.advanced_tools') }}</span>
                </div>
            </div>

            <h3>{{ __('sub-sphere::subscription.content.upgraded.getting_started_title') }}</h3>
            <p>{{ __('sub-sphere::subscription.content.upgraded.getting_started_text') }}</p>

            <h3>{{ __('sub-sphere::subscription.content.upgraded.billing_title') }}</h3>
            <p>{{ __('sub-sphere::subscription.content.upgraded.billing_text') }}</p>

            <p>{{ __('sub-sphere::subscription.content.upgraded.thank_you') }}</p>

            <p>{!! __('sub-sphere::subscription.content.regards') !!}</p>
        </div>

        <div class="footer">
            <p>{{ __('sub-sphere::subscription.content.upgraded.footer_note') }}</p>
            <p>{{ __('sub-sphere::subscription.content.upgraded.footer_question') }}</p>
        </div>
    </div>
</body>

</html>
