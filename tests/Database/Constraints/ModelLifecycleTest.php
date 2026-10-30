<?php

namespace Tests\Database\Constraints;

use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelLifecycleTest extends TestCase
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
    // SECTION 1: MODEL CRUD LIFECYCLE
    // ==========================================

    public function test_user_crud_lifecycle(): void
    {
        $admin = $this->createAdmin();
        $this->asUserWithToken($admin);

        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);

        $response = $this->postJson('/admin/users', [
            'name' => 'CRUD Test User',
            'email' => 'crud.test.'.uniqid().'@example.invalid',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRoleEnum::User->value,
            'profile_id' => $profile->id,
        ]);
        $response->assertStatus(201);
        $userId = $response->json('user.id');
        $this->assertDatabaseHas('users', ['id' => $userId]);

        $response = $this->patchJson("/admin/users/{$userId}", [
            'name' => 'Updated CRUD User',
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $userId, 'name' => 'Updated CRUD User']);
    }

    public function test_room_crud_lifecycle(): void
    {
        $admin = $this->createAdmin();
        $this->asUserWithToken($admin);

        $response = $this->postJson('/admin/rooms', [
            'name' => 'Test Room CRUD',
            'location' => 'Building A',
        ]);
        $response->assertStatus(201);
        $roomId = $response->json('room.id');
        $this->assertDatabaseHas('rooms', ['id' => $roomId]);

        $response = $this->patchJson("/admin/rooms/{$roomId}", [
            'name' => 'Updated Room',
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('rooms', ['id' => $roomId, 'name' => 'Updated Room']);
    }

    public function test_room_inactivate_preserves_data(): void
    {
        $admin = $this->createAdmin();
        $this->asUserWithToken($admin);

        $response = $this->postJson('/admin/rooms', [
            'name' => 'Inactivate Test',
            'location' => 'Building B',
        ]);
        $roomId = $response->json('room.id');

        $response = $this->patchJson("/admin/rooms/{$roomId}/inactive");
        $response->assertOk();
        $this->assertDatabaseHas('rooms', ['id' => $roomId, 'active' => false]);
    }

    public function test_equipment_crud_lifecycle_with_category(): void
    {
        $admin = $this->createAdmin();
        $this->asUserWithToken($admin);

        $room = Room::create(['name' => 'Eq Room', 'active' => true]);
        $category = EquipmentCategory::create(['name' => 'Test Cat', 'active' => true]);

        $response = $this->postJson('/admin/equipment', [
            'name' => 'Test Equipment',
            'serial' => 'EQ-'.uniqid(),
            'room_id' => $room->id,
            'category_id' => $category->id,
        ]);
        $response->assertStatus(201);
        $eqId = $response->json('equipment.id');
        $this->assertDatabaseHas('equipments', [
            'id' => $eqId,
            'category_id' => $category->id,
            'room_id' => $room->id,
        ]);
    }

    public function test_ticket_crud_full_lifecycle(): void
    {
        $user = $this->createCommonUser();
        $this->asUserWithToken($user);

        $response = $this->postJson('/tickets', [
            'title' => 'Persistence Test Ticket',
            'description' => 'Full lifecycle test',
            'priority' => 'média',
        ]);
        $response->assertStatus(201);
        $ticketId = $response->json('ticket.id');
        $this->assertDatabaseHas('tickets', ['id' => $ticketId]);

        $response = $this->getJson("/tickets/{$ticketId}");
        $response->assertOk();
    }

    // ==========================================
    // SECTION 3: SOFT DELETE & CASCADE BEHAVIOR
    // ==========================================

    public function test_ticket_soft_delete_preserves_record(): void
    {
        $user = $this->createCommonUser();
        $this->asUserWithToken($user);

        $response = $this->postJson('/tickets', [
            'title' => 'Soft Delete Test',
            'description' => 'Should be soft deleted',
            'priority' => 'baixa',
        ]);
        $ticketId = $response->json('ticket.id');

        $ticket = Ticket::find($ticketId);
        $ticket->delete();

        $this->assertSoftDeleted('tickets', ['id' => $ticketId]);

        $ticketFromDb = Ticket::withTrashed()->find($ticketId);
        $this->assertNotNull($ticketFromDb);
        $this->assertNotNull($ticketFromDb->deleted_at);
    }

    public function test_room_soft_delete_cascades_null_equipment_room_id(): void
    {
        $admin = $this->createAdmin();
        $this->asUserWithToken($admin);

        $response = $this->postJson('/admin/rooms', [
            'name' => 'Cascade Room',
            'location' => 'Test',
        ]);
        $roomId = $response->json('room.id');

        $this->postJson('/admin/equipment', [
            'name' => 'Cascade Equipment',
            'serial' => 'CAS-'.uniqid(),
            'room_id' => $roomId,
        ]);

        $room = Room::find($roomId);
        $room->delete();

        $this->assertSoftDeleted('rooms', ['id' => $roomId]);

        $equipment = Equipment::where('room_id', $roomId)->first();
        $this->assertNotNull($equipment->room_id, 'Equipment room_id preserved after soft delete');
    }

    // ==========================================
    // SECTION 4: MASS ASSIGNMENT PROTECTION
    // ==========================================

    public function test_ticket_cannot_mass_assign_id(): void
    {
        $user = $this->createCommonUser();
        $this->asUserWithToken($user);

        $response = $this->postJson('/tickets', [
            'title' => 'Mass Assign Test',
            'description' => 'Testing mass assignment',
            'priority' => 'baixa',
        ]);
        $ticketId = $response->json('ticket.id');

        $originalTicket = Ticket::find($ticketId);

        $response = $this->patchJson("/tickets/{$ticketId}/comments", [
            'comment' => 'Testing',
        ]);

        $this->assertDatabaseHas('tickets', ['id' => $ticketId]);
    }

    public function test_user_cannot_mass_assign_password_via_api_token_field(): void
    {
        $admin = $this->createAdmin();
        $this->asUserWithToken($admin);

        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        $response = $this->postJson('/admin/users', [
            'name' => 'Mass Test',
            'email' => 'mass.'.uniqid().'@example.invalid',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRoleEnum::User->value,
            'profile_id' => $profile->id,
            'api_token' => 'injected-token-value',
        ]);
        $response->assertStatus(201);
        $newUserId = $response->json('user.id');

        $user = User::find($newUserId);
        $this->assertNull($user->api_token, 'api_token must not be mass-assignable via store payload');
        $this->assertNotEquals('injected-token-value', $user->api_token);
    }

    // ==========================================
    // SECTION 11: UNIQUE CONSTRAINT ENFORCEMENT
    // ==========================================

    public function test_user_email_unique_constraint(): void
    {
        $this->createCommonUser();

        $this->expectException(QueryException::class);

        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        $existingUser = User::first();
        User::create([
            'name' => 'Dup Email',
            'email' => $existingUser->email,
            'password' => bcrypt('password'),
            'profile_id' => $profile->id,
        ]);
    }

    public function test_equipment_serial_unique_constraint(): void
    {
        $category = EquipmentCategory::create(['name' => 'Dup Cat', 'active' => true]);
        Equipment::create([
            'name' => 'First',
            'serial' => 'UNIQUE-SERIAL-001',
            'category_id' => $category->id,
            'active' => true,
        ]);

        $this->expectException(QueryException::class);

        Equipment::create([
            'name' => 'Second',
            'serial' => 'UNIQUE-SERIAL-001',
            'category_id' => $category->id,
            'active' => true,
        ]);
    }

    // ==========================================
    // SECTION 19: EMPTY & EDGE CASE DATA
    // ==========================================

    public function test_ticket_with_null_optional_fields(): void
    {
        $user = $this->createCommonUser();
        $this->asUserWithToken($user);

        $response = $this->postJson('/tickets', [
            'title' => 'Null Fields Test',
            'description' => 'Minimal data',
            'priority' => 'baixa',
        ]);
        $response->assertStatus(201);
        $ticketId = $response->json('ticket.id');

        $ticket = Ticket::find($ticketId);
        $this->assertNull($ticket->equipment_id);
        $this->assertNull($ticket->room_id);
        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->estimated_cost);
        $this->assertNull($ticket->technical_report);
    }

    public function test_equipment_without_category_creates_successfully(): void
    {
        $admin = $this->createAdmin();
        $this->asUserWithToken($admin);

        $response = $this->postJson('/admin/equipment', [
            'name' => 'No Category Equipment',
            'serial' => 'NC-'.uniqid(),
        ]);
        $response->assertStatus(201);
        $eqId = $response->json('equipment.id');

        $equipment = Equipment::find($eqId);
        $this->assertNull($equipment->category_id);
    }

    public function test_room_inactivate_sets_active_false(): void
    {
        $admin = $this->createAdmin();
        $this->asUserWithToken($admin);

        $response = $this->postJson('/admin/rooms', [
            'name' => 'Reactivate Room',
            'location' => 'Test',
        ]);
        $roomId = $response->json('room.id');

        $this->patchJson("/admin/rooms/{$roomId}/inactive")->assertOk();
        $this->assertDatabaseHas('rooms', ['id' => $roomId, 'active' => false]);

        $room = Room::find($roomId);
        $room->update(['active' => true]);
        $this->assertDatabaseHas('rooms', ['id' => $roomId, 'active' => true]);
    }
}
