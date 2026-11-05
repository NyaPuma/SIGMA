<?php

declare(strict_types=1);

namespace Tests\Feature\API\Controllers;

use App\Jobs\GenerateAiRecommendationJob;
use App\Models\Equipment;
use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Base\FeatureTestCase;

final class PublicTicketFeatureTest extends FeatureTestCase
{
    public function test_guest_can_view_public_ticket_creation_form(): void
    {
        // Arrange
        $equipment = Equipment::factory()->create();

        // Act
        $response = $this->get("/ticket/new?machine_id={$equipment->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertViewIs('ui.tickets.public.create');
        $response->assertSee($equipment->name);
    }

    public function test_guest_can_submit_public_ticket_with_photo(): void
    {
        // Arrange
        Queue::fake();
        Storage::fake('public');

        $admin = $this->createAdmin();
        $equipment = Equipment::factory()->create();
        $file = UploadedFile::fake()->image('avaria.png', 600, 400);

        // Act
        $response = $this->post('/ticket/store', [
            'equipment_id' => $equipment->id,
            'problem_type' => 'avaria',
            'description' => 'A máquina não liga ao carregar no interruptor principal.',
            'reporter_name' => 'João Silva',
            'reporter_contact' => '912345678',
            'photo' => $file,
        ]);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('tickets', [
            'equipment_id' => $equipment->id,
            'reporter_name' => 'João Silva',
            'reporter_contact' => '912345678',
        ]);

        $this->assertDatabaseHas('ticket_attachments', [
            'original_name' => 'avaria.png',
        ]);

        Queue::assertPushed(GenerateAiRecommendationJob::class);
    }

    public function test_public_ticket_submission_fails_validation_without_required_fields(): void
    {
        // Act
        $response = $this->from('/ticket/new')->post('/ticket/store', []);

        // Assert
        $response->assertRedirect('/ticket/new');
        $response->assertSessionHasErrors(['equipment_id', 'description']);
    }

    public function test_an_oversized_photo_is_refused_and_never_silently_dropped(): void
    {
        // Arrange: the configured limit, not a hardcoded one, decides what fits.
        Queue::fake();
        Storage::fake('public');
        config(['services.custom.upload.max_photo_size_kb' => 100]);

        $equipment = Equipment::factory()->create();

        // Act: 300 KB is far above the limit and below the old hardcoded 4096 KB.
        $response = $this->from("/ticket/new?machine_id={$equipment->id}")
            ->post('/ticket/store', [
                'equipment_id' => $equipment->id,
                'problem_type' => 'avaria',
                'description' => 'Perde pressão durante a operação.',
                'photo' => UploadedFile::fake()->image('avaria.png')->size(300),
            ]);

        // Assert: the reporter is told, and no ticket exists without its photo.
        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_attachments', 0);
    }

    public function test_a_photo_type_the_storage_layer_cannot_name_is_rejected_before_creation(): void
    {
        Queue::fake();
        Storage::fake('public');

        $equipment = Equipment::factory()->create();

        $response = $this->post('/ticket/store', [
            'equipment_id' => $equipment->id,
            'problem_type' => 'avaria',
            'description' => 'Vibracao anormal durante o arranque a frio.',
            'photo' => UploadedFile::fake()->image('avaria.bmp'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_a_photo_beyond_the_configured_dimensions_is_rejected(): void
    {
        Queue::fake();
        Storage::fake('public');

        $equipment = Equipment::factory()->create();

        $response = $this->post('/ticket/store', [
            'equipment_id' => $equipment->id,
            'problem_type' => 'avaria',
            'description' => 'Fumo saindo do quadro elétrico.',
            'photo' => UploadedFile::fake()->image('avaria.png', 5000, 5000),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_the_form_only_offers_file_types_the_validator_accepts(): void
    {
        $equipment = Equipment::factory()->create();

        $response = $this->get("/ticket/new?machine_id={$equipment->id}");

        $response->assertStatus(200);
        $response->assertSee('accept="image/jpeg,image/png,image/gif,image/webp"', false);
    }

    public function test_confirmation_page_is_only_reachable_through_the_signed_redirect(): void
    {
        // Arrange
        Queue::fake();
        $equipment = Equipment::factory()->create();

        // Act
        $response = $this->post('/ticket/store', [
            'equipment_id' => $equipment->id,
            'problem_type' => 'avaria',
            'description' => 'Perde pressão durante a operação e desliga-se sozinha.',
        ]);

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        // Assert: the redirect the reporter follows still works...
        $signed = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/ticket/success/'.$ticket->id, $signed);
        $this->assertStringContainsString('signature=', $signed);
        $this->get($signed)->assertStatus(200);

        // ...but the same page is unreadable by anyone who only guesses the identifier.
        $this->get("/ticket/success/{$ticket->id}")->assertForbidden();
    }
}
