<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('sub-sphere::subscription.subjects.subscription_changed') }}</title>
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

        .header.downgrade {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }

        .content {
            padding: 40px 30px;
        }

        .change-summary {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
            border-left: 4px solid #48bb78;
        }

        .change-summary.downgrade {
            border-left-color: #ed8936;
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

        .plan-box.new.downgrade {
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
            color: #48bb78;
            font-weight: 500;
        }

        .plan-price.downgrade {
            color: #ed8936;
        }

        .arrow {
            font-size: 24px;
            color: #48bb78;
        }

        .arrow.downgrade {
            color: #ed8936;
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
        <div class="header @if ($changeType === 'downgrade') downgrade @endif">
            <h1>
                @if ($changeType === 'upgrade')
                    🚀 {{ __('sub-sphere::subscription.headers.upgraded') }}
                @elseif($changeType === 'downgrade')
                    📋 {{ __('sub-sphere::subscription.headers.downgraded') }}
                @else
                    🔄 {{ __('sub-sphere::subscription.headers.changed') }}
                @endif
            </h1>
            <p>
                @if ($changeType === 'upgrade')
                    {{ __('sub-sphere::subscription.content.upgraded.subtitle', ['plan_name' => $newPlan->name]) }}
                @elseif($changeType === 'downgrade')
                    {{ __('sub-sphere::subscription.content.downgraded.subtitle', ['plan_name' => $newPlan->name]) }}
                @else
                    {{ __('sub-sphere::subscription.subjects.subscription_changed') }}
                @endif
            </p>
        </div>

        <div class="content">
            <p>{{ __('sub-sphere::subscription.content.greeting') }}</p>

            <p>
                @if ($changeType === 'upgrade')
                    {!! __('sub-sphere::subscription.content.upgraded.congratulations', ['plan_name' => $newPlan->name]) !!}
                @elseif($changeType === 'downgrade')
                    {!! __('sub-sphere::subscription.content.downgraded.change_message', ['plan_name' => $newPlan->name]) !!}
                @else
                    {!! __('sub-sphere::subscription.content.downgraded.change_message', ['plan_name' => $newPlan->name]) !!}
                @endif
            </p>

            <div class="change-summary @if ($changeType === 'downgrade') downgrade @endif">
                <h3>
                    @if ($changeType === 'downgrade')
                        {{ __('sub-sphere::subscription.content.downgraded.change_confirmation') }}
                    @else
                        {{ __('sub-sphere::subscription.content.downgraded.change_confirmation') }}
                    @endif
                </h3>

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

                    <div class="arrow @if ($changeType === 'downgrade') downgrade @endif">
                        @if ($changeType === 'upgrade')
                            ⬆️
                        @elseif($changeType === 'downgrade')
                            ⬇️
                        @else
                            ➡️
                        @endif
                    </div>

                    <div class="plan-box new @if ($changeType === 'downgrade') downgrade @endif">
                        <div class="plan-name">{{ $newPlan->name }}</div>
                        <div class="plan-price @if ($changeType === 'downgrade') downgrade @endif">
                            {{ number_format($newPlan->price, 2) }} {{ strtoupper($newPlan->currency) }}
                            @if ($newPlan->billing_interval)
                                / {{ $newPlan->billing_interval }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="effective-date">
                <strong>{{ __('sub-sphere::subscription.content.change_effective') }}</strong>
                {{ $effectiveDate->format('M j, Y \a\t g:i A') }}
            </div>

            @if ($newPlan->description)
                <h3>{{ __('sub-sphere::subscription.content.plan_includes') }}</h3>
                <p>{{ is_array($newPlan->description) ? $newPlan->description[app()->getLocale()] ?? ($newPlan->description['en'] ?? '') : $newPlan->description }}
                </p>
            @endif

            @if ($changeType === 'upgrade')
                <p>{{ __('sub-sphere::subscription.content.upgraded.getting_started_text') }}</p>
            @elseif($changeType === 'downgrade')
                <p>{{ __('sub-sphere::subscription.content.downgraded.need_help_text') }}</p>
            @endif

            <p>
                @if ($changeType === 'downgrade')
                    {{ __('sub-sphere::subscription.content.downgraded.thank_you') }}
                @else
                    {{ __('sub-sphere::subscription.content.upgraded.thank_you') }}
                @endif
            </p>

            <p>{!! __('sub-sphere::subscription.content.regards') !!}</p>
        </div>

        <div class="footer">
            <p>
                @if ($changeType === 'downgrade')
                    {{ __('sub-sphere::subscription.content.downgraded.footer_note') }}
                @else
                    {{ __('sub-sphere::subscription.content.upgraded.footer_note') }}
                @endif
            </p>
            <p>
                @if ($changeType === 'downgrade')
                    {{ __('sub-sphere::subscription.content.downgraded.footer_question') }}
                @else
                    {{ __('sub-sphere::subscription.content.upgraded.footer_question') }}
                @endif
            </p>
        </div>
    </div>
</body>

</html>
