<?php

namespace Tests\Database\Constraints;

use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\TicketAttachment;
use App\Models\TicketStatus;
use App\Models\TicketWorkflowHistory;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLookupData();
    }

    protected function seedLookupData(): void
    {
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Open->value], ['code' => 'ABERTA', 'description' => 'Aberta']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::InProgress->value], ['code' => 'EM_CURSO', 'description' => 'Em curso']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Closed->value], ['code' => 'FECHADA', 'description' => 'Fechada']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Cancelled->value], ['code' => 'CANCELADA', 'description' => 'Cancelada']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::PendingBudget->value], ['code' => 'PENDENTE_ORCAMENTO', 'description' => 'Pendente']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Rejected->value], ['code' => 'RECUSADA', 'description' => 'Recusada']);
    }

    protected function createAdmin(): User
    {
        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);
        $token = 'admin-persist-token-'.uniqid();
        $user = User::factory()->create([
            'profile_id' => $profile->id,
            'api_token' => $token,
        ]);
        $user->raw_token = $token;

        return $user;
    }

    protected function createTechnician(): User
    {
        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::Technician->value]);
        $token = 'tech-persist-token-'.uniqid();
        $user = User::factory()->create([
            'profile_id' => $profile->id,
            'api_token' => $token,
        ]);
        $user->raw_token = $token;

        return $user;
    }

    protected function createCommonUser(): User
    {
        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        $token = 'user-persist-token-'.uniqid();
        $user = User::factory()->create([
            'profile_id' => $profile->id,
            'api_token' => $token,
        ]);
        $user->raw_token = $token;

        return $user;
    }

    protected function asUserWithToken(User $user): static
    {
        return $this->withHeader('X-Auth-Token', $user->api_token)
            ->withHeader('Accept', 'application/json');
    }

    // ==========================================
    // SECTION 21: ATTACHMENT INTEGRITY
    // ==========================================

    public function test_attachment_belongs_to_ticket_and_user(): void
    {
        $admin = $this->createAdmin();
        $this->asUserWithToken($admin);

        $response = $this->postJson('/tickets', [
            'title' => 'Attachment Test Ticket',
            'description' => 'Test',
            'priority' => 'baixa',
        ]);
        $ticketId = $response->json('ticket.id');
        $user = $this->createCommonUser();

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticketId,
            'user_id' => $user->id,
            'file_name' => 'test.jpg',
            'path' => 'ticket_photos/test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
        ]);

        $this->assertNotNull($attachment->user);
        $this->assertEquals($user->id, $attachment->user_id);
        $this->assertNotNull($attachment->ticket);
        $this->assertEquals($ticketId, $attachment->ticket_id);
    }

    // ==========================================
    // SECTION 22: WORKFLOW HISTORY INTEGRITY
    // ==========================================

    public function test_workflow_history_model_crud(): void
    {
        $openStatus = TicketStatus::where('name', TicketStatusEnum::Open->value)->first();
        $inProgressStatus = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->first();

        $user = $this->createCommonUser();
        $this->asUserWithToken($user);

        $response = $this->postJson('/tickets', [
            'title' => 'Workflow History Test',
            'description' => 'Test',
            'priority' => 'baixa',
        ]);
        $ticketId = $response->json('ticket.id');

        $technician = $this->createTechnician();
        $wh = TicketWorkflowHistory::create([
            'ticket_id' => $ticketId,
            'origin_status_id' => $openStatus->id,
            'destination_status_id' => $inProgressStatus->id,
            'technician_id' => $technician->id,
            'comment' => 'Starting repair',
        ]);

        $this->assertNotNull($wh->ticket);
        $this->assertNotNull($wh->originStatus);
        $this->assertNotNull($wh->destinationStatus);
        $this->assertNotNull($wh->technician);
    }
}
