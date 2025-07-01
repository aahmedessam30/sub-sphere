<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('subscription.subjects.subscription_created', ['plan_name' => $planName]) }}</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .plan-details {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }

        .plan-name {
            font-size: 24px;
            font-weight: 600;
            color: #2d3748;
            margin: 0 0 10px 0;
        }

        .plan-price {
            font-size: 18px;
            color: #667eea;
            font-weight: 500;
        }

        .subscription-info {
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

        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
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
            .subscription-info {
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
            <h1>🎉 {{ __('subscription.headers.welcome') }}</h1>
            <p>{{ __('subscription.content.created.subtitle', ['plan_name' => $planName]) }}</p>
        </div>

        <div class="content">
            <p>{{ __('subscription.content.greeting') }}</p>

            <p>{{ __('subscription.content.created.welcome_message', ['plan_name' => $planName]) }}</p>

            <div class="plan-details">
                <div class="plan-name">{{ $planName }}</div>
                <div class="plan-price">
                    {{ number_format($subscription->plan->price ?? 0, 2) }}
                    {{ strtoupper($subscription->plan->currency ?? 'USD') }}
                    @if ($subscription->plan->billing_interval ?? null)
                        / {{ $subscription->plan->billing_interval }}
                    @endif
                </div>
            </div>

            <div class="subscription-info">
                <div class="info-item">
                    <div class="info-label">{{ __('subscription.content.start_date') }}</div>
                    <div class="info-value">{{ $subscription->starts_at->format('M j, Y') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">{{ __('subscription.content.status') }}</div>
                    <div class="info-value">{{ ucfirst($subscription->status) }}</div>
                </div>
                @if ($subscription->ends_at)
                    <div class="info-item">
                        <div class="info-label">{{ __('subscription.content.end_date') }}</div>
                        <div class="info-value">{{ $subscription->ends_at->format('M j, Y') }}</div>
                    </div>
                @endif
                @if ($subscription->trial_ends_at)
                    <div class="info-item">
                        <div class="info-label">{{ __('subscription.content.trial_ends') }}</div>
                        <div class="info-value">{{ $subscription->trial_ends_at->format('M j, Y') }}</div>
                    </div>
                @endif
            </div>

            @if ($subscription->plan->description)
                <h3>{{ __('subscription.content.created.includes_title') }}</h3>
                <p>{{ is_array($subscription->plan->description) ? $subscription->plan->description[app()->getLocale()] ?? ($subscription->plan->description['en'] ?? '') : $subscription->plan->description }}
                </p>
            @endif

            <p>{{ __('subscription.content.created.support_message') }}</p>

            <p>{!! __('subscription.content.regards') !!}</p>
        </div>

        <div class="footer">
            <p>{{ __('subscription.content.created.footer_note') }}</p>
            <p>{{ __('subscription.content.created.footer_error') }}</p>
        </div>
    </div>
</body>

</html>
