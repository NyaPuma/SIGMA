<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
    ) {}

    /**
     * Defines the notification channels (mail and database).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $ticketUrl = url("/ui/tickets/{$this->ticket->id}");

        return (new MailMessage)
            ->subject(__('notifications.subject_new_ticket', ['id' => $this->ticket->id]))
            ->greeting(__('notifications.greeting', ['name' => $notifiable instanceof User ? $notifiable->name : '']))
            ->line(__('notifications.new_ticket_line', ['title' => $this->ticket->title]))
            ->line(__('notifications.priority_line', ['priority' => ucfirst((string) $this->ticket->priority)]))
            ->action(__('notifications.action_view_ticket'), $ticketUrl)
            ->line(__('notifications.follow_up_line'))
            ->salutation(__('notifications.salutation'));
    }

    /**
     * Database representation of the notification (User Panel).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'title' => __('notifications.db_new_ticket_title'),
            'message' => __('notifications.db_new_ticket_message', [
                'id' => $this->ticket->id,
                'title' => $this->ticket->title,
            ]),
            'type' => 'info',
            'link' => "/ui/tickets/{$this->ticket->id}",
        ];
    }
}
