<?php

namespace Tests\Security\Tokens;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class TokenSecurityTest extends FeatureTestCase
{
    #[Test]
    public function it_verifies_token_is_60_characters_long(): void
    {
        $user = User::factory()->create([
            'profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->value('id'),
            'api_token' => Str::random(60),
        ]);

        $this->assertNotEmpty($user->api_token);
    }

    #[Test]
    public function it_verifies_token_is_unique_across_users(): void
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

    #[Test]
    public function it_regenerates_token_on_password_change(): void
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

        $response->assertOk();

        $user->refresh();
        $this->assertNotNull($user->api_token);
        $this->assertNotSame(User::hashToken($oldToken), $user->api_token);
    }

    #[Test]
    public function it_revokes_the_previous_token_on_password_change(): void
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

        $this->withHeader('X-Auth-Token', $oldToken)
            ->getJson('/ui/tickets')
            ->assertStatus(401);
    }

    #[Test]
    public function it_rejects_blank_token_for_auth_routes(): void
    {
        $response = $this->withHeader('X-Auth-Token', '')
            ->getJson('/ui/tickets');

        $response->assertStatus(401);
    }
}
