<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('subscription.subjects.trial_started') }}</title>
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
            background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
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

        .trial-highlight {
            background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
            color: white;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }

        .trial-days {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
        }

        .plan-details {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
            border-left: 4px solid #9f7aea;
        }

        .plan-name {
            font-size: 24px;
            font-weight: 600;
            color: #2d3748;
            margin: 0 0 10px 0;
        }

        .plan-price {
            font-size: 18px;
            color: #9f7aea;
            font-weight: 500;
        }

        .trial-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }

        .info-item {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 500;
        }

        .cta-section {
            background-color: #f7fafc;
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }

        .button {
            display: inline-block;
            background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin: 10px;
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
            .trial-info {
                grid-template-columns: 1fr;
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
            <h1>🎯 {{ __('subscription.headers.trial_started') }}</h1>
            <p>{{ __('subscription.content.trial.subtitle', ['plan_name' => $subscription->plan->name]) }}</p>
        </div>

        <div class="content">
            <p>{{ __('subscription.content.greeting') }}</p>

            <p>{{ __('subscription.content.trial.welcome_message', ['plan_name' => $subscription->plan->name]) }}</p>

            <div class="trial-highlight">
                <div>{{ __('subscription.content.trial.trial_includes') }}</div>
                <div class="trial-days">
                    {{ __('subscription.content.trial.trial_days', ['days' => $subscription->trial_ends_at->diffInDays($subscription->starts_at)]) }}
                </div>
                <div>{{ __('subscription.content.trial.trial_access', ['plan_name' => $subscription->plan->name]) }}
                </div>
            </div>

            <div class="plan-details">
                <div class="plan-name">{{ $subscription->plan->name }}</div>
                <div class="plan-price">
                    {{ number_format($subscription->plan->price, 2) }} {{ strtoupper($subscription->plan->currency) }}
                    @if ($subscription->plan->billing_interval)
                        / {{ $subscription->plan->billing_interval }}
                    @endif
                    <small style="color: #718096;">{{ __('subscription.content.trial.after_trial') }}</small>
                </div>
            </div>

            <div class="trial-info">
                <div class="info-item">
                    <div class="info-label">{{ __('subscription.content.trial.trial_info_started') }}</div>
                    <div class="info-value">{{ $subscription->starts_at->format('M j, Y') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">{{ __('subscription.content.trial.trial_info_ends') }}</div>
                    <div class="info-value">{{ $subscription->trial_ends_at->format('M j, Y') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">{{ __('subscription.content.trial.trial_info_status') }}</div>
                    <div class="info-value">{{ ucfirst($subscription->status) }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">{{ __('subscription.content.trial.trial_info_remaining') }}</div>
                    <div class="info-value">{{ $subscription->trial_ends_at->diffInDays(now()) }}
                        {{ __('subscription.content.days_remaining') }}</div>
                </div>
            </div>

            @if ($subscription->plan->description)
                <h3>{{ __('subscription.content.trial.what_you_get') }}</h3>
                <p>{{ is_array($subscription->plan->description) ? $subscription->plan->description[app()->getLocale()] ?? ($subscription->plan->description['en'] ?? '') : $subscription->plan->description }}
                </p>
            @endif

            <div class="cta-section">
                <h3>{{ __('subscription.content.trial.make_most_title') }}</h3>
                <p>{{ __('subscription.content.trial.make_most_text') }}</p>
            </div>

            <h3>{{ __('subscription.content.trial.important_title') }}</h3>
            <ul>
                <li>{!! __('subscription.content.trial.no_charges') !!}</li>
                <li>{!! __('subscription.content.trial.cancel_anytime') !!}</li>
                <li>{!! __('subscription.content.trial.auto_conversion') !!}</li>
                <li>{!! __('subscription.content.trial.full_access') !!}</li>
            </ul>

            <p>{{ __('subscription.content.trial.support_message') }}</p>

            <p>{!! __('subscription.content.regards') !!}</p>
        </div>

        <div class="footer">
            <p>{{ __('subscription.content.trial.footer_note') }}</p>
            <p>{{ __('subscription.content.trial.footer_reminder') }}</p>
        </div>
    </div>
</body>

</html>
