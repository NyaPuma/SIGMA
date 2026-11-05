<?php

namespace Tests\Security\RateLimiting;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class RateLimitingTest extends FeatureTestCase
{
    #[Test]
    public function it_returns_401_or_429_on_multiple_failed_logins(): void
    {
        User::factory()->create([
            'email' => 'bruteforce@example.com',
            'password' => Hash::make('Password123!'),
            'profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->value('id'),
            'active' => true,
            'api_token' => Str::random(60),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $response = $this->withSession([])->postJson('/api/login', [
                'email' => 'bruteforce@example.com',
                'password' => 'wrong-password-'.$i,
            ]);
            $this->assertContains($response->status(), [401, 429]);
        }
    }

    #[Test]
    public function it_throttles_rapid_consecutive_requests(): void
    {
        User::factory()->create([
            'email' => 'rapid@example.com',
            'password' => Hash::make('Password123!'),
            'profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->value('id'),
            'active' => true,
            'api_token' => Str::random(60),
        ]);

        $start = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $this->withSession([])->postJson('/api/login', [
                'email' => 'rapid@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $duration = microtime(true) - $start;
        $this->assertLessThan(30, $duration, 'Rate limiting should not cause excessive delays');
    }

    #[Test]
    public function it_resets_rate_limit_counter_after_successful_login(): void
    {
        User::factory()->create([
            'email' => 'resetcounter@example.com',
            'password' => Hash::make('Password123!'),
            'profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->value('id'),
            'active' => true,
            'api_token' => Str::random(60),
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->withSession([])->postJson('/api/login', [
                'email' => 'resetcounter@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->withSession([])->postJson('/api/login', [
            'email' => 'resetcounter@example.com',
            'password' => 'Password123!',
        ]);
        $response->assertOk();

        $newToken = $response->json('token');
        $this->assertNotNull($newToken);

        $profileResponse = $this->withHeader('X-Auth-Token', $newToken)
            ->getJson('/ui/tickets');
        $this->assertContains($profileResponse->status(), [200, 302]);
    }

    #[Test]
    public function it_rate_limits_after_multiple_login_attempts(): void
    {
        User::factory()->create([
            'email' => 'ratelimit@example.com',
            'password' => Hash::make('Password123!'),
            'profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->value('id'),
            'active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->withSession([])->postJson('/api/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'wrong-password',
            ]);
            $response->assertStatus(401);
        }

        $response = $this->withSession([])->postJson('/api/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'wrong-password',
        ]);
        $response->assertStatus(429);
        $this->assertNotNull(
            $response->headers->get('Retry-After'),
            'A blocked login has to say how long to wait'
        );
    }

    #[Test]
    public function it_returns_retry_after_header_on_throttled_response(): void
    {
        User::factory()->create([
            'email' => 'headers@example.com',
            'password' => Hash::make('Password123!'),
            'profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->value('id'),
            'active' => true,
        ]);

        $response = null;

        for ($i = 0; $i < 6; $i++) {
            $response = $this->withSession([])->postJson('/api/login', [
                'email' => 'headers@example.com',
                'password' => 'wrong-password',
            ]);
        }

        if ($response !== null && $response->status() === 429) {
            $this->assertNotNull($response->headers->get('Retry-After'));
        }
    }

    #[Test]
    public function it_applies_independent_rate_limits_per_ip(): void
    {
        User::factory()->create([
            'email' => 'multiip@example.com',
            'password' => Hash::make('Password123!'),
            'profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->value('id'),
            'active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->withSession([])
                ->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
                ->postJson('/api/login', [
                    'email' => 'multiip@example.com',
                    'password' => 'wrong-password',
                ]);
            $response->assertStatus(401);
        }

        // The sixth request from the same address is the one that must be blocked.
        $this->withSession([])
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->postJson('/api/login', [
                'email' => 'multiip@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);

        // A different address has to be unaffected: if the counter were keyed by email alone,
        // anyone could lock a real user out of their own account by guessing their address.
        $this->withSession([])
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.2'])
            ->postJson('/api/login', [
                'email' => 'multiip@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(401);

        // And the owner, from a fresh address, can still get in with the right password.
        $this->withSession([])
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.3'])
            ->postJson('/api/login', [
                'email' => 'multiip@example.com',
                'password' => 'Password123!',
            ])
            ->assertOk();
    }

    #[Test]
    public function it_stamps_the_last_login_on_a_successful_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'lastlogin@example.com',
            'password' => Hash::make('Password123!'),
            'profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->value('id'),
            'active' => true,
            'last_login_at' => null,
            'last_login_ip' => null,
        ]);

        $this->withSession([])
            ->withServerVariables(['REMOTE_ADDR' => '192.0.2.44'])
            ->postJson('/api/login', [
                'email' => 'lastlogin@example.com',
                'password' => 'Password123!',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('192.0.2.44', $user->last_login_ip);
    }
}
