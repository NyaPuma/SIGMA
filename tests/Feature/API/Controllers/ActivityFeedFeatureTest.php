<?php

declare(strict_types=1);

namespace Tests\Feature\API\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\Audit;
use App\Models\Ticket;
use Tests\Base\FeatureTestCase;

final class ActivityFeedFeatureTest extends FeatureTestCase
{
    public function test_authenticated_user_can_fetch_activity_feed(): void
    {
        // Arrange
        $user = $this->createAdmin();

        Audit::create([
            'user_id' => $user->id,
            'auditable_type' => Ticket::class,
            'auditable_id' => 1,
            'event' => 'created',
            'new_values' => ['title' => 'Impressora avariada'],
        ]);

        // Act
        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->actingAs($user)
            ->getJson('/api/activities');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'description',
                    'time_ago',
                    'icon_bg',
                    'dot_color',
                ],
            ]);
    }

    public function test_technician_cannot_fetch_activity_feed(): void
    {
        // The feed exposes other users' names and record titles, so it is admin-only (AuditPolicy).
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/api/activities')
            ->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        // Act
        $response = $this->getJson('/api/activities');

        // Assert
        $response->assertStatus(401);
    }
}
