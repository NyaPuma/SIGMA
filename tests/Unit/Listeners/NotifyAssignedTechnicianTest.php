<?php

namespace Tests\Unit\Listeners;

use App\Enums\TicketStatusEnum;
use App\Events\TicketStatusUpdated;
use App\Listeners\NotifyAssignedTechnician;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\NewTicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotifyAssignedTechnicianTest extends TestCase
{
    use RefreshDatabase;

    private function technician(): User
    {
        $profile = UserProfile::firstOrCreate(['name' => 'technician']);

        return User::factory()->create([
            'profile_id' => $profile->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function it_notifies_the_assigned_technician(): void
    {
        Notification::fake();

        $technician = $this->technician();
        $ticket = Ticket::factory()->create(['assigned_to' => $technician->id]);

        $listener = new NotifyAssignedTechnician;
        $listener->handle(new TicketStatusUpdated(
            $ticket,
            TicketStatusEnum::Open,
            TicketStatusEnum::InProgress,
        ));

        Notification::assertSentTo($technician, NewTicketNotification::class);
    }

    #[Test]
    public function it_does_nothing_when_no_technician_is_assigned(): void
    {
        Notification::fake();

        $ticket = Ticket::factory()->create(['assigned_to' => null]);

        $listener = new NotifyAssignedTechnician;
        $listener->handle(new TicketStatusUpdated(
            $ticket,
            TicketStatusEnum::Open,
            TicketStatusEnum::InProgress,
        ));

        Notification::assertNothingSent();
    }
}
