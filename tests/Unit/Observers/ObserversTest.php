<?php

namespace Tests\Unit\Observers;

use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Events\TicketCreated;
use App\Events\TicketStatusChanged;
use App\Models\Audit;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\TicketStatusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;
use Tests\Concerns\CreatesTickets;

class ObserversTest extends FeatureTestCase
{
    use CreatesTickets;

    protected function setUp(): void
    {
        parent::setUp();

        app(TicketStatusService::class)->flush();
    }

    // --- UserObserver ---

    #[Test]
    public function creating_a_user_without_profile_assigns_the_default_profile(): void
    {
        $user = User::factory()->create(['profile_id' => null, 'active' => true]);

        $this->assertNotNull($user->profile_id);
        $this->assertSame(UserRoleEnum::User->value, UserProfile::find($user->profile_id)->name);
    }

    #[Test]
    public function creating_a_user_with_a_valid_profile_keeps_it(): void
    {
        $adminProfile = UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);

        $user = User::factory()->create(['profile_id' => $adminProfile->id, 'active' => true]);

        $this->assertSame($adminProfile->id, $user->profile_id);
    }

    #[Test]
    public function creating_a_user_with_an_invalid_profile_falls_back_to_the_default(): void
    {
        $user = User::factory()->create(['profile_id' => 999999, 'active' => true]);

        $this->assertSame(UserRoleEnum::User->value, UserProfile::find($user->profile_id)->name);
    }

    #[Test]
    public function updating_a_user_to_an_invalid_profile_falls_back_to_the_default(): void
    {
        $adminProfile = UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);
        $user = User::factory()->create(['profile_id' => $adminProfile->id, 'active' => true]);

        $user->update(['profile_id' => 999999]);

        $this->assertSame(UserRoleEnum::User->value, UserProfile::find($user->fresh()->profile_id)->name);
    }

    // --- TicketObserver ---

    #[Test]
    public function creating_a_ticket_dispatches_ticket_created_event(): void
    {
        Event::fake([TicketCreated::class]);

        $ticket = $this->createTicket();

        Event::assertDispatched(TicketCreated::class, fn (TicketCreated $event) => $event->ticket->is($ticket));
    }

    #[Test]
    public function creating_a_ticket_invalidates_the_analytics_cache(): void
    {
        // Mirrors AnalyticsDashboardService::getDashboardPayload(), which keys by locale.
        $key = 'analytics_dashboard_payload:'.app()->getLocale();
        Cache::put($key, 'cached-value');

        $this->createTicket();

        $this->assertNull(Cache::get($key));
    }

    #[Test]
    public function changing_ticket_status_dispatches_status_changed_event(): void
    {
        Event::fake([TicketStatusChanged::class]);

        $ticket = $this->createTicket();
        $closedStatusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Closed);

        $ticket->update(['status_id' => $closedStatusId]);

        Event::assertDispatched(
            TicketStatusChanged::class,
            fn (TicketStatusChanged $event) => $event->ticket->is($ticket)
                && $event->newStatus === TicketStatusEnum::Closed,
        );
    }

    #[Test]
    public function updating_without_status_change_does_not_dispatch_status_changed_event(): void
    {
        Event::fake([TicketStatusChanged::class]);

        $ticket = $this->createTicket();
        $ticket->update(['description' => 'Descrição atualizada']);

        Event::assertNotDispatched(TicketStatusChanged::class);
    }

    #[Test]
    public function deleting_a_ticket_invalidates_the_analytics_cache(): void
    {
        $key = 'analytics_dashboard_payload:'.app()->getLocale();
        Cache::put($key, 'cached-value');

        $ticket = $this->createTicket();
        $ticket->delete();

        $this->assertNull(Cache::get($key));
    }

    #[Test]
    public function assigning_a_technician_stamps_the_waiting_end_time(): void
    {
        $ticket = $this->createTicket();
        $technician = User::factory()->create();

        $ticket->update(['assigned_to' => $technician->id]);

        $this->assertNotNull($ticket->fresh()->assigned_at);
    }

    #[Test]
    public function the_first_assignment_keeps_its_stamp_when_the_technician_changes(): void
    {
        $firstTechnician = User::factory()->create();
        $secondTechnician = User::factory()->create();

        Carbon::setTestNow(now()->subHours(2));
        $ticket = $this->createTicket(['assigned_to' => $firstTechnician->id]);
        $stampedAt = $ticket->assigned_at;
        Carbon::setTestNow();

        $ticket->update(['assigned_to' => $secondTechnician->id]);

        $this->assertEquals(
            $stampedAt->toDateTimeString(),
            $ticket->fresh()->assigned_at->toDateTimeString(),
        );
    }

    #[Test]
    public function an_assignment_with_an_explicit_stamp_keeps_that_moment(): void
    {
        $seededAt = now()->subDays(5)->startOfMinute();
        $technician = User::factory()->create();

        $ticket = $this->createTicket([
            'assigned_to' => $technician->id,
            'assigned_at' => $seededAt,
        ]);

        $this->assertEquals($seededAt->toDateTimeString(), $ticket->assigned_at->toDateTimeString());
    }

    // --- AuditObserver / Audit model guard ---

    #[Test]
    public function audit_records_are_immutable(): void
    {
        $audit = Audit::create([
            'auditable_type' => Ticket::class,
            'auditable_id' => 1,
            'event' => 'created',
            'new_values' => ['title' => 'x'],
        ]);

        $this->expectException(LogicException::class);
        $audit->update(['event' => 'updated']);
    }

    #[Test]
    public function audit_records_cannot_be_deleted(): void
    {
        $audit = Audit::create([
            'auditable_type' => Ticket::class,
            'auditable_id' => 1,
            'event' => 'created',
            'new_values' => ['title' => 'x'],
        ]);

        $this->expectException(LogicException::class);
        $audit->delete();
    }
}
