<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\FollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Push notification sent when a follow-up becomes due today.
 */
class FollowUpDueNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param FollowUp $followUp
     */
    public function __construct(
        private readonly FollowUp $followUp,
    ) {}

    /**
     * Get the notification delivery channels.
     *
     * @param mixed $notifiable
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Build the web push representation of the notification.
     *
     * @param mixed $notifiable
     * @return WebPushMessage
     */
    public function toWebPush(mixed $notifiable): WebPushMessage
    {
        return (new WebPushMessage())
            ->title('Follow-up due')
            ->body('Follow-up due: ' . $this->followUp->description)
            ->icon('/icons/icon-192.svg')
            ->tag('follow-up-' . $this->followUp->id)
            ->data(['url' => '/follow-ups']);
    }
}
