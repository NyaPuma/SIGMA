<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Notifications\NewTicketNotification;
use App\Notifications\TicketStatusChanged;
use Tests\Base\FeatureTestCase;

/**
 * As notificações por e-mail do ticket não podem tentar escrever no canal `database`
 * do framework: a tabela `notifications` pertence ao painel interno e tem um esquema
 * incompatível (id auto-incremento, user_id/title obrigatórios).
 */
final class TicketNotificationChannelTest extends FeatureTestCase
{
    public function test_new_ticket_notification_is_delivered_without_a_framework_database_row(): void
    {
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $ticket = Ticket::factory()->create(['user_id' => $technician->id]);

        $technician->notify(new NewTicketNotification($ticket));

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_status_changed_notification_is_delivered_without_a_framework_database_row(): void
    {
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $ticket = Ticket::factory()->create(['user_id' => $technician->id]);

        $technician->notify(new TicketStatusChanged(
            $ticket,
            TicketStatusEnum::InProgress->value,
            TicketStatusEnum::Closed->value,
        ));

        $this->assertDatabaseCount('notifications', 0);
    }
}
