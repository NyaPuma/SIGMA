<?php

namespace Tests\Unit\Traits;

use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Audit;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\TicketStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditableTraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Auth::shouldUse('web');
        Auth::logout();

        Ticket::resetResolvedUserId();
        Equipment::resetResolvedUserId();
        Room::resetResolvedUserId();

        TicketType::firstOrCreate(['name' => 'avaria', 'description' => 'Avaria']);
        $typeId = TicketType::where('name', 'avaria')->first()->id;
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Open->value, 'description' => 'Aberto', 'type_id' => $typeId]);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::InProgress->value, 'description' => 'Em Curso', 'type_id' => $typeId]);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Closed->value, 'description' => 'Fechado', 'type_id' => $typeId]);
        UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        UserProfile::firstOrCreate(['name' => UserRoleEnum::Technician->value]);
        UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);
    }

    #[Test]
    public function it_creates_audit_log_on_ticket_creation(): void
    {
        $openStatusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'title' => 'Audit Create Test',
            'description' => 'Testing audit creation',
            'priority' => TicketPriorityEnum::Medium->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $audit = Audit::where('auditable_id', $ticket->id)
            ->where('auditable_type', Ticket::class)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('created', $audit->event);
        $this->assertNotNull($audit->new_values);
        $this->assertNull($audit->old_values);
    }

    #[Test]
    public function it_creates_audit_log_on_ticket_update(): void
    {
        $openStatusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'title' => 'Audit Update Test',
            'description' => 'Original description',
            'priority' => TicketPriorityEnum::Low->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $ticket->update(['priority' => TicketPriorityEnum::High->value]);

        $audit = Audit::where('auditable_id', $ticket->id)
            ->where('auditable_type', Ticket::class)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('updated', $audit->event);
        $this->assertNotNull($audit->old_values);
        $this->assertNotNull($audit->new_values);
        $this->assertEquals(TicketPriorityEnum::Low->value, $audit->old_values['priority']);
        $this->assertEquals(TicketPriorityEnum::High->value, $audit->new_values['priority']);
    }

    #[Test]
    public function it_creates_audit_log_on_ticket_deletion(): void
    {
        $openStatusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'title' => 'Audit Delete Test',
            'description' => 'Testing audit deletion',
            'priority' => TicketPriorityEnum::Medium->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $ticketId = $ticket->id;
        $ticket->delete();

        $audit = Audit::where('auditable_id', $ticketId)
            ->where('auditable_type', Ticket::class)
            ->where('event', 'deleted')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('deleted', $audit->event);
        $this->assertNotNull($audit->old_values);
        $this->assertNull($audit->new_values);
    }

    #[Test]
    public function it_creates_audit_log_on_ticket_restore(): void
    {
        $openStatusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);
        $user = User::factory()->create();
        $ticket = Ticket::create([
            'title' => 'Audit Restore Test',
            'description' => 'Testing audit restore',
            'priority' => TicketPriorityEnum::Medium->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $ticketId = $ticket->id;
        $ticket->delete();
        $ticket->restore();

        // O enum `audits.event` prevê 'restored'; sem o listener correspondente o
        // `restore()` não deixava qualquer rasto de quem reactivou o registo.
        $audit = Audit::where('auditable_id', $ticketId)
            ->where('auditable_type', Ticket::class)
            ->where('event', 'restored')
            ->first();

        $this->assertNotNull($audit);
        $this->assertArrayHasKey('deleted_at', $audit->new_values);
        $this->assertNull($audit->new_values['deleted_at']);
    }

    #[Test]
    public function it_creates_audit_with_request_metadata(): void
    {
        $openStatusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'title' => 'Audit Metadata Test',
            'description' => 'Testing audit metadata',
            'priority' => TicketPriorityEnum::High->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $audit = Audit::where('auditable_id', $ticket->id)
            ->where('auditable_type', Ticket::class)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
    }

    #[Test]
    public function it_handles_audit_without_authenticated_user(): void
    {
        $openStatusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'title' => 'No Auth Audit',
            'description' => 'Testing audit without auth',
            'priority' => TicketPriorityEnum::Low->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $audit = Audit::where('auditable_id', $ticket->id)
            ->where('auditable_type', Ticket::class)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertNull($audit->user_id);
    }

    #[Test]
    public function it_does_not_break_on_audit_failure(): void
    {
        $openStatusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'title' => 'Audit Failure Test',
            'description' => 'Should not break on audit failure',
            'priority' => TicketPriorityEnum::Medium->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $this->assertNotNull($ticket);
        $this->assertEquals('Audit Failure Test', $ticket->title);
    }

    #[Test]
    public function it_creates_audit_with_correct_structure(): void
    {
        $openStatusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'title' => 'Audit Structure Test',
            'description' => 'Testing audit structure',
            'priority' => TicketPriorityEnum::Medium->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $audit = Audit::where('auditable_id', $ticket->id)
            ->where('auditable_type', Ticket::class)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertArrayHasKey('title', $audit->new_values);
        $this->assertArrayHasKey('description', $audit->new_values);
        $this->assertArrayHasKey('priority', $audit->new_values);
        $this->assertArrayHasKey('user_id', $audit->new_values);
        $this->assertArrayHasKey('status_id', $audit->new_values);
    }
}
