<?php

namespace Tests\Feature\API\Controllers;

use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\PhotoUploadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create profiles needed for the tests
        UserProfile::create(['name' => UserRoleEnum::User->value]);
        UserProfile::create(['name' => UserRoleEnum::Technician->value]);
        UserProfile::create(['name' => UserRoleEnum::Admin->value]);

        // Create ticket statuses
        $this->artisan('db:seed', ['--class' => 'TicketLookupSeeder', '--force' => true]);
    }

    private function createUserWithToken(string $profileName): User
    {
        $profile = UserProfile::where('name', $profileName)->firstOrFail();

        return User::factory()->create([
            'profile_id' => $profile->id,
            'api_token' => Str::random(60),
        ]);
    }

    public function test_ticket_photo_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithToken(UserRoleEnum::User->value);

        $openStatusId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Avaria teste',
            'description' => 'Descrição da avaria',
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/api/tickets/'.$ticket->id.'/photos', [
                'photo' => UploadedFile::fake()->image('damage.jpg', 800, 600),
            ]);

        $response->assertStatus(201);
        $this->assertStringEndsWith('.jpg', $response->json('attachment.file_name'));
        $this->assertStringStartsWith('ticket_photos/', $response->json('attachment.path'));
        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_ticket_photo_upload_requires_photo_field(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $openStatusId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Avaria teste',
            'description' => 'Descrição da avaria',
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/api/tickets/'.$ticket->id.'/photos', [
                // without photo
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['photo']]);
    }

    public function test_ticket_photo_upload_rejects_non_image_file(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $openStatusId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Avaria teste',
            'description' => 'Descrição da avaria',
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/api/tickets/'.$ticket->id.'/photos', [
                'photo' => UploadedFile::fake()->create('damage.txt', 10, 'text/plain'),
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['photo']]);
    }

    public function test_ticket_photo_upload_rejects_when_ticket_does_not_exist(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithToken(UserRoleEnum::User->value);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/api/tickets/999999/photos', [
                'photo' => UploadedFile::fake()->create('damage.jpg', 10, 'image/jpeg'),
            ]);

        $response->assertStatus(404);
        $response->assertJsonIsObject();
    }

    public function test_ticket_photo_upload_rejects_oversized_file(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $openId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Photo size test',
            'description' => 'Testing photo size limit',
            'status_id' => $openId,
            'opened_at' => now(),
        ]);

        $maxKb = PhotoUploadPolicy::maxSizeKb();

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/api/tickets/'.$ticket->id.'/photos', [
                'photo' => UploadedFile::fake()->image('large.jpg', 800, 600)->size(($maxKb + 100) * 1024),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['photo']]);
    }

    public function test_ticket_photo_upload_rejects_excessive_dimensions(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $openId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Photo dimension test',
            'description' => 'Testing photo dimension limit',
            'status_id' => $openId,
            'opened_at' => now(),
        ]);

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/api/tickets/'.$ticket->id.'/photos', [
                'photo' => UploadedFile::fake()->image('huge.jpg', 5000, 5000),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['photo']]);
    }
}
