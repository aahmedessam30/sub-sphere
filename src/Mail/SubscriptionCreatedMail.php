<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Mail;

use AhmedEssam\SubSphere\Events\SubscriptionCreated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Subscription Created Mail
 * 
 * Welcome email sent when a new subscription is created.
 */
class SubscriptionCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly SubscriptionCreated $subscriptionCreated
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $planName = $this->subscriptionCreated->plan->getLocalizedName();

        $subject = $this->subscriptionCreated->hasTrial()
            ? __('sub-sphere::subscription.subjects.trial_started', ['plan_name' => $planName])
            : __('sub-sphere::subscription.subjects.subscription_created', ['plan_name' => $planName]);

        return new Envelope(
            subject: $subject,
            tags: ['subscription', 'welcome'],
            metadata: [
                'subscription_id' => $this->subscriptionCreated->subscription->id,
                'plan_id' => $this->subscriptionCreated->plan->id,
                'has_trial' => $this->subscriptionCreated->hasTrial(),
                'is_recurring' => $this->subscriptionCreated->isRecurring(),
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $template = $this->subscriptionCreated->hasTrial()
            ? "sub-sphere::emails.subscription.trial-started"
            : "sub-sphere::emails.subscription.created";

        $planName = $this->subscriptionCreated->plan->getLocalizedName();

        return new Content(
            view: $template,
            with: [
                'subscriber' => $this->subscriptionCreated->subscriber,
                'subscription' => $this->subscriptionCreated->subscription,
                'plan' => $this->subscriptionCreated->plan,
                'details' => $this->subscriptionCreated->details,
                'hasTrial' => $this->subscriptionCreated->hasTrial(),
                'isRecurring' => $this->subscriptionCreated->isRecurring(),
                'formattedSummary' => $this->subscriptionCreated->getFormattedSummary(),
                'planName' => $planName,
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
