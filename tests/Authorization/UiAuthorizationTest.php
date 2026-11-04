<?php

namespace Tests\Authorization;

use App\Enums\UserRoleEnum;
use App\Models\Part;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        UserProfile::create(['name' => UserRoleEnum::User->value]);
        UserProfile::create(['name' => UserRoleEnum::Technician->value]);
        UserProfile::create(['name' => UserRoleEnum::Admin->value]);
        $this->artisan('db:seed', ['--class' => 'TicketLookupSeeder', '--force' => true]);
    }

    public function test_guest_is_redirected_from_ui_pages(): void
    {
        $this->get('/ui')->assertRedirect('/ui/login');
        $this->get('/ui/tickets')->assertRedirect('/ui/login');
        $this->get('/ui/equipments')->assertRedirect('/ui/login');
        $this->get('/ui/tickets/1')->assertRedirect('/ui/login');
        $this->get('/ui/users')->assertRedirect('/ui/login');
        $this->get('/ui/audits')->assertRedirect('/ui/login');
        $this->get('/ui/analytics')->assertRedirect('/ui/login');
    }

    public function test_common_user_is_blocked_from_analytics_and_admin_ui(): void
    {
        $userProfile = UserProfile::where('name', UserRoleEnum::User->value)->firstOrFail();
        $user = User::factory()->create([
            'profile_id' => $userProfile->id,
            'api_token' => Str::random(60),
        ]);

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->getJson('/ui/analytics')
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->getJson('/ui/audits')
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->getJson('/ui/users')
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->getJson('/admin/users')
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->getJson('/admin/audits')
            ->assertStatus(403);
    }

    public function test_technician_can_access_shared_ui_but_not_admin_backoffice(): void
    {
        $techProfile = UserProfile::where('name', UserRoleEnum::Technician->value)->firstOrFail();
        $technician = User::factory()->create([
            'profile_id' => $techProfile->id,
            'api_token' => Str::random(60),
        ]);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->get('/ui')
            ->assertOk();

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->get('/ui/analytics')
            ->assertStatus(302);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->get('/ui/audits')
            ->assertStatus(302);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->get('/ui/equipments')
            ->assertOk();

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->get('/ui/tickets/1')
            ->assertOk();

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/admin/users')
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/admin/audits')
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/admin/equipment')
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/admin/rooms')
            ->assertStatus(403);
    }

    public function test_common_user_can_access_general_ui_pages_but_not_shared_backoffice_pages(): void
    {
        $userProfile = UserProfile::where('name', UserRoleEnum::User->value)->firstOrFail();
        $user = User::factory()->create([
            'profile_id' => $userProfile->id,
            'api_token' => Str::random(60),
        ]);

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->get('/ui')
            ->assertOk();

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->get('/ui/tickets')
            ->assertOk();

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->get('/ui/equipments')
            ->assertOk();

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->getJson('/ui/users')
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->getJson('/ui/audits')
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $user->api_token)
            ->getJson('/ui/analytics')
            ->assertStatus(403);
    }

    public function test_stock_ui_pages_are_restricted_to_admin_and_technician(): void
    {
        $userProfile = UserProfile::where('name', UserRoleEnum::User->value)->firstOrFail();
        $user = User::factory()->create([
            'profile_id' => $userProfile->id,
            'api_token' => Str::random(60),
        ]);

        $techProfile = UserProfile::where('name', UserRoleEnum::Technician->value)->firstOrFail();
        $technician = User::factory()->create([
            'profile_id' => $techProfile->id,
            'api_token' => Str::random(60),
        ]);

        $part = Part::factory()->create();

        foreach (['/ui/stock', '/ui/stock/parts', '/ui/stock/suppliers', '/ui/stock/movements', "/ui/stock/parts/{$part->id}"] as $uri) {
            $this->withHeader('X-Auth-Token', $user->api_token)
                ->get($uri)
                ->assertStatus(403);

            $this->withHeader('X-Auth-Token', $technician->api_token)
                ->get($uri)
                ->assertOk();
        }
    }

    public function test_admin_can_access_pulse_dashboard(): void
    {
        $adminProfile = UserProfile::where('name', UserRoleEnum::Admin->value)->firstOrFail();
        $admin = User::factory()->create([
            'profile_id' => $adminProfile->id,
            'api_token' => Str::random(60),
        ]);

        $this->actingAs($admin, 'web')
            ->get('/pulse')
            ->assertOk();
    }

    public function test_common_user_is_blocked_from_pulse_dashboard(): void
    {
        $userProfile = UserProfile::where('name', UserRoleEnum::User->value)->firstOrFail();
        $user = User::factory()->create([
            'profile_id' => $userProfile->id,
            'api_token' => Str::random(60),
        ]);

        $this->actingAs($user, 'web')
            ->get('/pulse')
            ->assertStatus(403);
    }

    public function test_technician_is_blocked_from_pulse_dashboard(): void
    {
        $techProfile = UserProfile::where('name', UserRoleEnum::Technician->value)->firstOrFail();
        $technician = User::factory()->create([
            'profile_id' => $techProfile->id,
            'api_token' => Str::random(60),
        ]);

        $this->actingAs($technician, 'web')
            ->get('/pulse')
            ->assertStatus(403);
    }

    public function test_guest_is_blocked_from_pulse_dashboard(): void
    {
        $this->get('/pulse')
            ->assertStatus(403);
    }
}
