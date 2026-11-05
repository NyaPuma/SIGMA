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
     * Defines the notification channels (mail).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Só "mail": o canal `database` do framework escreve um `id` UUID e não preenche
        // `user_id`/`title`, e a tabela `notifications` deste projecto é do painel interno
        // (auto-incremento + colunas obrigatórias) — o INSERT falhava sempre. O painel é
        // escrito à mão por `NotificationCreatorService` nos eventos que a aplicação
        // mostra (fecho, override de prioridade, orçamento, stock baixo); este nunca foi
        // um deles, por isso nada muda para quem vê o painel.
        return ['mail'];
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
}
