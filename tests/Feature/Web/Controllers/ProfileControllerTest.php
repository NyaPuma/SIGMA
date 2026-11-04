<?php

namespace Tests\Feature\Web\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class ProfileControllerTest extends FeatureTestCase
{
    private function createProfileUser(string $password = 'Password123!'): User
    {
        return $this->createUserWithPassword(
            UserRoleEnum::User->value,
            'perfil@example.com',
            $password,
        );
    }

    #[Test]
    public function change_password_updates_password_with_current_password(): void
    {
        $user = $this->createProfileUser();

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/password/change', [
                'current_password' => 'Password123!',
                'new_password' => 'Nova-password-1',
            ]);

        $response->assertOk()
            ->assertJson(['message' => __('messages.Password alterada com sucesso.')]);

        $this->assertTrue(Hash::check('Nova-password-1', $user->fresh()->password));
    }

    #[Test]
    public function change_password_rejects_wrong_current_password(): void
    {
        $user = $this->createProfileUser();

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/password/change', [
                'current_password' => 'wrong-password',
                'new_password' => 'Nova-password-1',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function change_password_requires_authentication(): void
    {
        $response = $this->postJson('/password/change', [
            'current_password' => 'Password123!',
            'new_password' => 'Nova-password-1',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function update_profile_updates_name_and_returns_user_resource(): void
    {
        $user = $this->createProfileUser();

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/profile/update', [
                'name' => 'Novo Nome',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => __('messages.Perfil atualizado com sucesso.'),
                're_authentication_required' => false,
            ])
            ->assertJsonStructure(['user' => ['id', 'name', 'email']]);

        $this->assertEquals('Novo Nome', $user->fresh()->name);
        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
        // A profile edit that leaves the password alone must not invalidate the token.
        $this->assertSame($user->api_token, $user->fresh()->api_token);
    }

    #[Test]
    public function update_profile_rotates_the_api_token_when_the_password_changes(): void
    {
        $user = $this->createProfileUser();
        $tokenBefore = $user->api_token;

        $response = $this->withHeader('X-Auth-Token', $tokenBefore)
            ->postJson('/profile/update', [
                'current_password' => 'Password123!',
                'password' => 'Nova-password-1',
                'password_confirmation' => 'Nova-password-1',
            ]);

        $response->assertOk()->assertJson(['re_authentication_required' => true]);

        $this->assertNotSame($tokenBefore, $user->fresh()->api_token);
        $this->withHeader('X-Auth-Token', $tokenBefore)
            ->getJson('/ui/tickets')
            ->assertStatus(401);
    }

    #[Test]
    public function update_profile_changes_password_with_valid_current_password(): void
    {
        $user = $this->createProfileUser();

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/profile/update', [
                'current_password' => 'Password123!',
                'password' => 'Nova-password-1',
                'password_confirmation' => 'Nova-password-1',
            ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('Nova-password-1', $user->fresh()->password));
    }

    #[Test]
    public function update_profile_rejects_password_change_without_current_password(): void
    {
        $user = $this->createProfileUser();

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/profile/update', [
                'password' => 'Nova-password-1',
                'password_confirmation' => 'Nova-password-1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
    }

    #[Test]
    public function update_profile_rejects_password_change_with_wrong_current_password(): void
    {
        $user = $this->createProfileUser();

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/profile/update', [
                'current_password' => 'wrong-password',
                'password' => 'Nova-password-1',
                'password_confirmation' => 'Nova-password-1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
    }

    #[Test]
    public function update_profile_requires_authentication(): void
    {
        $response = $this->postJson('/profile/update', [
            'name' => 'Intruso',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function update_profile_validation_edge_cases(): void
    {
        $user = $this->createProfileUser();

        $send = fn (array $payload) => $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/profile/update', $payload);

        // Password confirmation does not match
        $send([
            'current_password' => 'Password123!',
            'password' => 'Nova-password-1',
            'password_confirmation' => 'Outra-password-1',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Weak password
        $send([
            'current_password' => 'Password123!',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Name containing only spaces
        $send(['name' => '   '])->assertStatus(422)->assertJsonValidationErrors(['name']);
    }
}
