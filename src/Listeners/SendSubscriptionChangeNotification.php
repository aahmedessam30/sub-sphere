<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Listeners;

use AhmedEssam\SubSphere\Events\SubscriptionChanged;
use AhmedEssam\SubSphere\Mail\SubscriptionPlanChangedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send Subscription Change Notification
 * 
 * Listener that sends email notifications when subscriptions are changed.
 */
class SendSubscriptionChangeNotification implements ShouldQueue
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
    public function handle(SubscriptionChanged $event): void
    {
        try {
            // Send email notification
            $this->sendEmailNotification($event);

            // Log the change for audit trail
            $this->logSubscriptionChange($event);

        } catch (\Exception $e) {
            Log::error('Failed to send subscription change notification', [
                'subscription_id' => $event->subscription->id,
                'subscriber_id' => $event->subscriber->getKey(),
                'subscriber_type' => get_class($event->subscriber),
                'change_type' => $event->changeSummary['change_type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            // Optionally re-throw to retry if using queue
            // throw $e;
        }
    }

    /**
     * Send email notification to the subscriber
     */
    protected function sendEmailNotification(SubscriptionChanged $event): void
    {
        // Get subscriber's email
        $email = $this->getSubscriberEmail($event->subscriber);

        if (!$email) {
            Log::warning('No email found for subscriber', [
                'subscriber_id' => $event->subscriber->getKey(),
                'subscriber_type' => get_class($event->subscriber),
            ]);
            return;
        }

        // Send the notification email
        Mail::to($email)->send(new SubscriptionPlanChangedMail($event));

        Log::info('Subscription change notification sent', [
            'subscription_id' => $event->subscription->id,
            'email' => $email,
            'change_type' => $event->getChangeTypeLabel(),
        ]);
    }

    /**
     * Log subscription change for audit trail
     */
    protected function logSubscriptionChange(SubscriptionChanged $event): void
    {
        Log::info('Subscription plan changed', [
            'subscription_id' => $event->subscription->id,
            'subscriber_id' => $event->subscriber->getKey(),
            'subscriber_type' => get_class($event->subscriber),
            'old_plan_id' => $event->oldPlan->id,
            'old_plan_name' => $event->oldPlan->name,
            'new_plan_id' => $event->newPlan->id,
            'new_plan_name' => $event->newPlan->name,
            'change_type' => $event->changeSummary['change_type'] ?? 'unknown',
            'change_summary' => $event->changeSummary,
            'formatted_summary' => $event->getFormattedSummary(),
        ]);
    }

    /**
     * Get subscriber's email address
     */
    protected function getSubscriberEmail($subscriber): ?string
    {
        // Try common email properties
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
    public function failed(SubscriptionChanged $event, \Throwable $exception): void
    {
        Log::error('Subscription change notification job failed', [
            'subscription_id' => $event->subscription->id,
            'subscriber_id' => $event->subscriber->getKey(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}