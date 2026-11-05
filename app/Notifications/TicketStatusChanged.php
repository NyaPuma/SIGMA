<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TicketStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}

    /**
     * Defines the notification channels (mail).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Ver NewTicketNotification::via() — o canal `database` do framework não é
        // compatível com o esquema da tabela `notifications` deste projecto, por isso o
        // INSERT falhava a cada tentativa do job. As linhas do painel são escritas por
        // `NotificationCreatorService` (fecho, override de prioridade, orçamento, stock
        // baixo), o que já acontecia independentemente deste canal.
        return ['mail'];
    }

    /**
     * Mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $oldLabel = $this->resolveStatusLabel($this->oldStatus);
        $newLabel = $this->resolveStatusLabel($this->newStatus);

        $subject = __('notifications.subject_status_changed', [
            'id' => $this->ticket->id,
            'status' => $newLabel,
        ]);

        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('notifications.greeting', ['name' => $notifiable instanceof User ? $notifiable->name : '']))
            ->line(__('notifications.status_updated_line'))
            ->line(__('notifications.ticket_line', ['id' => $this->ticket->id, 'title' => $this->ticket->title]))
            ->line(__('notifications.old_status_line', ['status' => $oldLabel]))
            ->line(__('notifications.new_status_line', ['status' => $newLabel]))
            ->action(__('notifications.action_view_ticket'), url("/ui/tickets/{$this->ticket->id}"))
            ->line(__('notifications.thanks_line'));
    }

    /**
     * Resolves the human-friendly status label from a string or enum.
     */
    private function resolveStatusLabel(string $status): string
    {
        $enum = TicketStatusEnum::tryFrom($status);

        if ($enum !== null) {
            return $enum->label();
        }

        foreach (TicketStatusEnum::cases() as $case) {
            if ($case->label() === $status) {
                return $case->label();
            }
        }

        return $status;
    }
}
