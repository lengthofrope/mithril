<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Push notification sent when a task deadline falls on today.
 */
class TaskDeadlineNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param Task $task
     */
    public function __construct(
        private readonly Task $task,
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
            ->title('Task deadline today')
            ->body('Task deadline today: ' . $this->task->title)
            ->icon('/icons/icon-192.svg')
            ->tag('task-deadline-' . $this->task->id)
            ->data(['url' => '/tasks']);
    }
}
