<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('subscription.subjects.subscription_downgraded') }}</title>
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
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
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

        .change-notice {
            background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
            color: #9a3412;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid #fed7aa;
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
            border-color: #ed8936;
            box-shadow: 0 0 0 2px rgba(237, 137, 54, 0.1);
        }

        .plan-name {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .plan-price {
            font-size: 16px;
            color: #ed8936;
            font-weight: 500;
        }

        .arrow {
            font-size: 24px;
            color: #ed8936;
        }

        .savings-highlight {
            background-color: #f0f9ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #0ea5e9;
            text-align: center;
        }

        .savings-amount {
            font-size: 24px;
            color: #0ea5e9;
            font-weight: 600;
        }

        .effective-date {
            background-color: #edf2f7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }

        .important-info {
            background-color: #fef2f2;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #ef4444;
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
            <h1>📋 {{ __('subscription.headers.downgraded') }}</h1>
            <p>{{ __('subscription.content.downgraded.subtitle', ['plan_name' => $newPlan->name]) }}</p>
        </div>

        <div class="content">
            <p>{{ __('subscription.content.greeting') }}</p>

            <p>{!! __('subscription.content.downgraded.change_message', ['plan_name' => $newPlan->name]) !!}</p>

            <div class="change-notice">
                <strong>{{ __('subscription.content.downgraded.change_confirmation') }}</strong><br>
                {{ __('subscription.content.downgraded.change_confirmation_text', ['plan_name' => $newPlan->name]) }}
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

                <div class="arrow">⬇️</div>

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
                <strong>{{ __('subscription.content.change_effective') }}</strong>
                {{ $effectiveDate->format('M j, Y \a\t g:i A') }}
            </div>

            @if ($oldPlan->price > $newPlan->price)
                <div class="savings-highlight">
                    <h3 style="margin-top: 0; color: #0ea5e9;">
                        {{ __('subscription.content.downgraded.savings_title') }}</h3>
                    <div class="savings-amount">
                        ${{ number_format($oldPlan->price - $newPlan->price, 2) }}
                    </div>
                    <p style="margin-bottom: 0;">{{ __('subscription.content.downgraded.savings_text') }}</p>
                </div>
            @endif

            @if ($newPlan->description)
                <h3>{{ __('subscription.content.plan_includes') }}</h3>
                <p>{{ is_array($newPlan->description) ? $newPlan->description[app()->getLocale()] ?? ($newPlan->description['en'] ?? '') : $newPlan->description }}
                </p>
            @endif

            <div class="important-info">
                <h3 style="margin-top: 0; color: #dc2626;">{{ __('subscription.content.downgraded.important_title') }}
                </h3>
                <ul style="margin-bottom: 0;">
                    <li>{!! __('subscription.content.downgraded.feature_access') !!}</li>
                    <li>{!! __('subscription.content.downgraded.billing_info') !!}</li>
                    <li>{!! __('subscription.content.downgraded.data_info') !!}</li>
                    <li>{!! __('subscription.content.downgraded.support_info') !!}</li>
                </ul>
            </div>

            <h3>{{ __('subscription.content.downgraded.need_help_title') }}</h3>
            <p>{{ __('subscription.content.downgraded.need_help_text') }}</p>

            <h3>{{ __('subscription.content.downgraded.billing_changes_title') }}</h3>
            <p>{{ __('subscription.content.downgraded.billing_changes_text', ['plan_name' => $newPlan->name]) }}</p>

            <p>{{ __('subscription.content.downgraded.thank_you') }}</p>

            <p>{!! __('subscription.content.regards') !!}</p>
        </div>

        <div class="footer">
            <p>{{ __('subscription.content.downgraded.footer_note') }}</p>
            <p>{{ __('subscription.content.downgraded.footer_question') }}</p>
        </div>
    </div>
</body>

</html>
