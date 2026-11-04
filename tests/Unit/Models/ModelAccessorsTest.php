<?php

namespace Tests\Unit\Models;

use App\Enums\TicketStatusEnum;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketWorkflowHistory;
use App\Models\User;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;
use Tests\Concerns\CreatesTickets;

class ModelAccessorsTest extends FeatureTestCase
{
    use CreatesTickets;

    protected function setUp(): void
    {
        parent::setUp();

        app(TicketStatusService::class)->flush();
    }

    #[Test]
    public function ticket_calculates_budget_pause_minutes(): void
    {
        $ticket = $this->createTicket([
            'budget_requested_at' => now()->subMinutes(45),
            'budget_decided_at' => now(),
        ]);

        $this->assertSame(45, $ticket->budget_pause_minutes);
    }

    #[Test]
    public function ticket_budget_pause_minutes_is_zero_when_not_decided(): void
    {
        $ticket = $this->createTicket([
            'budget_requested_at' => now()->subMinutes(45),
            'budget_decided_at' => null,
        ]);

        $this->assertSame(0, $ticket->budget_pause_minutes);
    }

    #[Test]
    public function ticket_budget_pause_minutes_is_zero_when_decided_before_requested(): void
    {
        $ticket = $this->createTicket([
            'budget_requested_at' => now(),
            'budget_decided_at' => now()->subMinutes(45),
        ]);

        $this->assertSame(0, $ticket->budget_pause_minutes);
    }

    #[Test]
    public function ticket_open_scope_filters_only_open_tickets(): void
    {
        $open = $this->createTicket();
        $closed = $this->createTicketWithStatus(TicketStatusEnum::Closed->value);

        $ids = Ticket::open()->pluck('id')->all();

        $this->assertContains($open->id, $ids);
        $this->assertNotContains($closed->id, $ids);
    }

    #[Test]
    public function ticket_for_technician_scope_filters_by_assigned_user(): void
    {
        $technician = $this->createTechnician();
        $other = $this->createTechnician();

        $assigned = $this->createTicket(['assigned_to' => $technician->id]);
        $this->createTicket(['assigned_to' => $other->id]);

        $ids = Ticket::forTechnician($technician->id)->pluck('id')->all();

        $this->assertContains($assigned->id, $ids);
        $this->assertCount(1, $ids);
    }

    #[Test]
    public function notification_mark_as_read_and_unread_update_state(): void
    {
        $user = $this->createAdmin();
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'Aviso',
            'message' => 'Conteúdo',
            'type' => 'system',
            'is_read' => false,
        ]);

        $this->assertTrue($notification->markAsRead());
        $this->assertTrue($notification->fresh()->is_read);
        $this->assertTrue($notification->fresh()->markAsUnread());
        $this->assertFalse($notification->fresh()->is_read);
    }

    #[Test]
    public function notification_scopes_filter_read_and_unread(): void
    {
        $user = $this->createAdmin();

        Notification::create(['user_id' => $user->id, 'title' => 'Unread', 'message' => 'x', 'type' => 'system', 'is_read' => false]);
        Notification::create(['user_id' => $user->id, 'title' => 'Read', 'message' => 'x', 'type' => 'system', 'is_read' => true]);

        $this->assertSame(['Unread'], Notification::unread()->pluck('title')->all());
        $this->assertSame(['Read'], Notification::read()->pluck('title')->all());
    }

    #[Test]
    public function user_hash_token_is_deterministic_hmac(): void
    {
        $expected = hash_hmac('sha256', 'abc', (string) config('app.key'));

        $this->assertSame($expected, User::hashToken('abc'));
        $this->assertSame(User::hashToken('abc'), User::hashToken('abc'));
        $this->assertNotSame(User::hashToken('abc'), User::hashToken('abd'));
    }

    #[Test]
    public function workflow_history_renders_transition_label_and_time_ago(): void
    {
        $history = TicketWorkflowHistory::create([
            'ticket_id' => $this->createTicket()->id,
            'origin_status_id' => $this->statusId(TicketStatusEnum::Open),
            'destination_status_id' => $this->statusId(TicketStatusEnum::InProgress),
        ]);

        $this->assertSame(
            'aberta ➔ em curso',
            strtolower($history->transition_label),
        );
        $this->assertNotEmpty($history->time_ago);
    }

    #[Test]
    public function comment_reports_edited_only_after_update(): void
    {
        $comment = TicketComment::create([
            'ticket_id' => $this->createTicket()->id,
            'user_id' => $this->createAdmin()->id,
            'comment' => 'Original',
            'is_internal' => false,
        ]);

        $this->assertFalse($comment->is_edited);

        $this->travelTo(now()->addMinutes(5));
        $comment->update(['comment' => 'Editado']);

        $this->assertTrue($comment->fresh()->is_edited);
    }

    #[Test]
    public function attachment_formats_size_and_detects_images(): void
    {
        Storage::fake('public');

        $attachment = TicketAttachment::create([
            'ticket_id' => $this->createTicket()->id,
            'user_id' => $this->createAdmin()->id,
            'file_name' => 'foto.png',
            'path' => 'attachments/foto.png',
            'disk' => 'public',
            'mime_type' => 'image/png',
            'size' => 2048,
        ]);

        $this->assertSame('2 KB', $attachment->formatted_size);
        $this->assertTrue($attachment->is_image);
        $this->assertSame('/storage/attachments/foto.png', $attachment->url);

        $pdf = TicketAttachment::create([
            'ticket_id' => $this->createTicket()->id,
            'user_id' => $this->createAdmin()->id,
            'file_name' => 'doc.pdf',
            'path' => 'attachments/doc.pdf',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->assertFalse($pdf->is_image);
    }

    private function statusId(TicketStatusEnum $status): int
    {
        return app(TicketStatusService::class)->getByName($status);
    }
}
