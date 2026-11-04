<?php

namespace Tests\Security\Tokens;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        UserProfile::create(['name' => UserRoleEnum::User->value]);
        UserProfile::create(['name' => UserRoleEnum::Technician->value]);
        UserProfile::create(['name' => UserRoleEnum::Admin->value]);
    }

    public function test_token_is_60_characters_long(): void
    {
        $user = User::factory()->create([
            'profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->value('id'),
            'api_token' => Str::random(60),
        ]);

        $this->assertEquals(60, strlen($user->api_token));
    }

    public function test_token_is_unique_across_users(): void
    {
        $profileId = UserProfile::where('name', UserRoleEnum::User->value)->value('id');
        $token1 = Str::random(60);
        $token2 = Str::random(60);

        $this->assertNotEquals($token1, $token2);

        User::factory()->create(['profile_id' => $profileId, 'api_token' => $token1]);
        User::factory()->create(['profile_id' => $profileId, 'api_token' => $token2]);

        $this->assertDatabaseHas('users', ['api_token' => $token1]);
        $this->assertDatabaseHas('users', ['api_token' => $token2]);
    }

    public function test_password_change_rotates_the_api_token(): void
    {
        $profileId = UserProfile::where('name', UserRoleEnum::User->value)->value('id');
        $oldToken = Str::random(60);

        $user = User::factory()->create([
            'profile_id' => $profileId,
            'password' => Hash::make('current-password'),
            'api_token' => User::hashToken($oldToken),
        ]);

        $response = $this->withHeader('X-Auth-Token', $oldToken)
            ->postJson('/api/password/change', [
                'current_password' => 'current-password',
                'new_password' => 'Password123!',
            ]);

        $response->assertOk()->assertJson(['re_authentication_required' => true]);

        $user->refresh();
        $this->assertNotSame(User::hashToken($oldToken), $user->api_token);
    }

    public function test_old_token_is_rejected_after_password_change(): void
    {
        $profileId = UserProfile::where('name', UserRoleEnum::User->value)->value('id');
        $oldToken = Str::random(60);

        $user = User::factory()->create([
            'profile_id' => $profileId,
            'password' => Hash::make('current-password'),
            'api_token' => User::hashToken($oldToken),
        ]);

        $this->withHeader('X-Auth-Token', $oldToken)
            ->postJson('/api/password/change', [
                'current_password' => 'current-password',
                'new_password' => 'Password123!',
            ]);

        // Rotating the token is what makes a password change a real remediation: a token
        // that leaked before the change must stop working with it.
        $this->withHeader('X-Auth-Token', $oldToken)
            ->getJson('/ui/tickets')
            ->assertStatus(401);
    }

    public function test_blank_token_rejected_for_auth_routes(): void
    {
        $response = $this->withHeader('X-Auth-Token', '')
            ->getJson('/ui/tickets');

        $response->assertStatus(401);
    }
}
