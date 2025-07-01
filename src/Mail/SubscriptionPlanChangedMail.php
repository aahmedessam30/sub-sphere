<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Mail;

use AhmedEssam\SubSphere\Events\SubscriptionChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Subscription Plan Changed Mail
 * 
 * Email notification sent when a user's subscription plan is changed.
 */
class SubscriptionPlanChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly SubscriptionChanged $subscriptionChanged
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match (true) {
            $this->subscriptionChanged->isUpgrade() => __('sub-sphere::subscription.subjects.subscription_upgraded'),
            $this->subscriptionChanged->isDowngrade() => __('sub-sphere::subscription.subjects.subscription_downgraded'),
            default => __('sub-sphere::subscription.subjects.subscription_changed'),
        };

        return new Envelope(
            subject: $subject,
            tags: ['subscription', 'plan-change'],
            metadata: [
                'subscription_id' => $this->subscriptionChanged->subscription->id,
                'change_type' => $this->subscriptionChanged->changeSummary['change_type'] ?? 'unknown',
                'old_plan_id' => $this->subscriptionChanged->oldPlan->id,
                'new_plan_id' => $this->subscriptionChanged->newPlan->id,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $template = match (true) {
            $this->subscriptionChanged->isUpgrade() => "sub-sphere::emails.subscription.upgraded",
            $this->subscriptionChanged->isDowngrade() => "sub-sphere::emails.subscription.downgraded",
            default => "sub-sphere::emails.subscription.changed",
        };

        $oldPlanName = $this->subscriptionChanged->oldPlan->getLocalizedName();

        $newPlanName = $this->subscriptionChanged->newPlan->getLocalizedName();

        return new Content(
            view: $template,
            with: [
                'subscriber' => $this->subscriptionChanged->subscriber,
                'subscription' => $this->subscriptionChanged->subscription,
                'oldPlan' => $this->subscriptionChanged->oldPlan,
                'newPlan' => $this->subscriptionChanged->newPlan,
                'changeSummary' => $this->subscriptionChanged->changeSummary,
                'isUpgrade' => $this->subscriptionChanged->isUpgrade(),
                'isDowngrade' => $this->subscriptionChanged->isDowngrade(),
                'changeType' => $this->subscriptionChanged->getChangeTypeLabel(),
                'formattedSummary' => $this->subscriptionChanged->getFormattedSummary(),
                'oldPlanName' => $oldPlanName,
                'newPlanName' => $newPlanName,
                'effectiveDate' => now(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
