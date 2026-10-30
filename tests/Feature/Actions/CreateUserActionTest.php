<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateUserAction;
use App\DTOs\StoreUserData;
use App\Models\UserProfile;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user(): void
    {
        UserProfile::factory()->create(['name' => 'user']);
        $action = new CreateUserAction(app(UserService::class));

        $data = new StoreUserData(
            name: 'Test User',
            email: 'test@example.com',
            password: 'password123',
            profileId: null,
            active: true,
        );

        $user = $action->execute($data);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'active' => true,
        ]);

        $this->assertNotNull($user->profile_id);
    }

    public function test_it_persists_profile_id_in_database(): void
    {
        UserProfile::factory()->create(['name' => 'user']);
        $action = new CreateUserAction(app(UserService::class));

        $data = new StoreUserData(
            name: 'Persisted User',
            email: 'persisted@example.com',
            password: 'password123',
            profileId: null,
            active: true,
        );

        $user = $action->execute($data);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_id' => $user->profile_id,
        ]);
    }

    public function test_it_uses_provided_profile_id_when_given(): void
    {
        $profile = UserProfile::factory()->create(['name' => 'technician']);
        $action = new CreateUserAction(app(UserService::class));

        $data = new StoreUserData(
            name: 'Technical User',
            email: 'tech@example.com',
            password: 'password123',
            profileId: $profile->id,
            active: true,
        );

        $user = $action->execute($data);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_id' => $profile->id,
        ]);
    }
}
