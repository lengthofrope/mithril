<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Bila;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Push notification sent when a bila is scheduled for today.
 */
class BilaReminderNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param Bila $bila
     */
    public function __construct(
        private readonly Bila $bila,
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
        $memberName = $this->bila->teamMember?->name ?? 'a team member';

        return (new WebPushMessage())
            ->title('Bila scheduled today')
            ->body('Bila scheduled today with ' . $memberName)
            ->icon('/icons/icon-192.svg')
            ->tag('bila-' . $this->bila->id)
            ->data(['url' => '/bilas/' . $this->bila->id]);
    }
}
