<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Listeners;

use AhmedEssam\SubSphere\Events\SubscriptionCreated;
use AhmedEssam\SubSphere\Mail\SubscriptionCreatedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send Subscription Created Notification
 * 
 * Listener that sends welcome email when new subscriptions are created.
 */
class SendSubscriptionCreatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SubscriptionCreated $event): void
    {
        try {
            // Send welcome email
            $this->sendWelcomeEmail($event);

            // Log subscription creation
            $this->logSubscriptionCreated($event);

        } catch (\Exception $e) {
            Log::error('Failed to send subscription created notification', [
                'subscription_id' => $event->subscription->id,
                'subscriber_id'   => $event->subscriber->getKey(),
                'subscriber_type' => get_class($event->subscriber),
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send welcome email to the new subscriber
     */
    protected function sendWelcomeEmail(SubscriptionCreated $event): void
    {
        $email = $this->getSubscriberEmail($event->subscriber);

        if (!$email) {
            Log::warning('No email found for new subscriber', [
                'subscriber_id'   => $event->subscriber->getKey(),
                'subscriber_type' => get_class($event->subscriber),
            ]);
            return;
        }

        Mail::to($email)->send(new SubscriptionCreatedMail($event));

        Log::info('Welcome email sent to new subscriber', [
            'subscription_id' => $event->subscription->id,
            'email'           => $email,
            'plan_name'       => $event->plan->name,
            'has_trial'       => $event->hasTrial(),
        ]);
    }

    /**
     * Log subscription creation for audit trail
     */
    protected function logSubscriptionCreated(SubscriptionCreated $event): void
    {
        $planName = $event->plan->getLocalizedName();

        Log::info(__('sub-sphere::subscription.logs.subscription_created', [
            'email' => $this->getSubscriberEmail($event->subscriber),
            'plan'  => $planName,
        ]), [
            'subscription_id'   => $event->subscription->id,
            'subscriber_id'     => $event->subscriber->getKey(),
            'subscriber_type'   => get_class($event->subscriber),
            'plan_id'           => $event->plan->id,
            'plan_name'         => $planName,
            'details'           => $event->details,
            'has_trial'         => $event->hasTrial(),
            'is_recurring'      => $event->isRecurring(),
            'formatted_summary' => $event->getFormattedSummary(),
        ]);
    }

    /**
     * Get subscriber's email address
     */
    protected function getSubscriberEmail($subscriber): ?string
    {
        if (isset($subscriber->email)) {
            return $subscriber->email;
        }

        if (isset($subscriber->email_address)) {
            return $subscriber->email_address;
        }

        if (method_exists($subscriber, 'getEmailForNotifications')) {
            return $subscriber->getEmailForNotifications();
        }

        return null;
    }

    /**
     * Handle a job failure.
     */
    public function failed(SubscriptionCreated $event, \Throwable $exception): void
    {
        Log::error('Subscription created notification job failed', [
            'subscription_id' => $event->subscription->id,
            'subscriber_id'   => $event->subscriber->getKey(),
            'error'           => $exception->getMessage(),
            'trace'           => $exception->getTraceAsString(),
        ]);
    }
}