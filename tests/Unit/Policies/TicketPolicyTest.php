<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserProfile;
use App\Policies\TicketPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\DatabaseTestCase;

class TicketPolicyTest extends DatabaseTestCase
{
    private TicketPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TicketPolicy;
        $this->seedUserProfiles();
    }

    private function seedUserProfiles(): void
    {
        UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);
        UserProfile::firstOrCreate(['name' => UserRoleEnum::Technician->value]);
        UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
    }

    #[Test]
    public function admin_can_view_any_ticket(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);

        $this->assertTrue($this->policy->viewAny($admin));
    }

    #[Test]
    public function technician_can_view_any_ticket(): void
    {
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);

        $this->assertTrue($this->policy->viewAny($technician));
    }

    #[Test]
    public function user_can_view_any_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);

        $this->assertTrue($this->policy->viewAny($user));
    }

    #[Test]
    public function admin_can_view_ticket(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->view($admin, $ticket));
    }

    #[Test]
    public function technician_can_view_ticket(): void
    {
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->view($technician, $ticket));
    }

    #[Test]
    public function user_can_view_own_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->view($user, $ticket));
    }

    #[Test]
    public function user_cannot_view_other_users_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $otherUser = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->view($user, $ticket));
    }

    #[Test]
    public function only_user_can_cancel_own_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->cancel($user, $ticket));
    }

    #[Test]
    public function user_cannot_cancel_other_users_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $otherUser = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->cancel($user, $ticket));
    }

    #[Test]
    public function technician_cannot_cancel_ticket(): void
    {
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertFalse($this->policy->cancel($technician, $ticket));
    }

    #[Test]
    public function admin_cannot_cancel_ticket(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertFalse($this->policy->cancel($admin, $ticket));
    }

    #[Test]
    public function technician_can_start_ticket(): void
    {
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->start($technician, $ticket));
    }

    #[Test]
    public function user_cannot_start_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertFalse($this->policy->start($user, $ticket));
    }

    #[Test]
    public function technician_can_close_ticket(): void
    {
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $otherTechnician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $assigned = Ticket::factory()->create(['assigned_to' => $technician->id]);

        $this->assertTrue($this->policy->close($technician, $assigned));
        $this->assertFalse($this->policy->close($otherTechnician, $assigned));
        $this->assertFalse($this->policy->close($technician, Ticket::factory()->create()));
    }

    #[Test]
    public function user_cannot_close_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertFalse($this->policy->close($user, $ticket));
    }

    #[Test]
    public function technician_can_reopen_ticket(): void
    {
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->reopen($technician, $ticket));
    }

    #[Test]
    public function admin_can_reopen_ticket(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->reopen($admin, $ticket));
    }

    #[Test]
    public function user_cannot_reopen_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertFalse($this->policy->reopen($user, $ticket));
    }

    #[Test]
    public function only_admin_can_approve_budget(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->approveBudget($admin, $ticket));
    }

    #[Test]
    public function technician_cannot_approve_budget(): void
    {
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertFalse($this->policy->approveBudget($technician, $ticket));
    }

    #[Test]
    public function user_cannot_approve_budget(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertFalse($this->policy->approveBudget($user, $ticket));
    }

    #[Test]
    public function only_admin_or_technician_can_update_a_ticket(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($admin, $ticket));
        $this->assertTrue($this->policy->update($technician, $ticket));
        $this->assertFalse($this->policy->update($user, $ticket));
    }

    #[Test]
    public function only_admin_can_delete_a_ticket(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $ticket));
        $this->assertFalse($this->policy->delete($technician, $ticket));
    }

    #[Test]
    public function owner_can_comment_and_attach_photos_to_own_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $otherUser = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $own = Ticket::factory()->create(['user_id' => $user->id]);
        $other = Ticket::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($this->policy->comment($user, $own));
        $this->assertFalse($this->policy->comment($user, $other));
        $this->assertTrue($this->policy->attachPhoto($user, $own));
        $this->assertFalse($this->policy->attachPhoto($user, $other));
        $this->assertTrue($this->policy->deletePhoto($user, $own));
        $this->assertFalse($this->policy->deletePhoto($user, $other));
    }

    #[Test]
    public function staff_can_comment_on_any_ticket(): void
    {
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->comment($technician, $ticket));
        $this->assertTrue($this->policy->attachPhoto($technician, $ticket));
        $this->assertTrue($this->policy->deletePhoto($technician, $ticket));
    }

    #[Test]
    public function only_owner_or_staff_can_schedule_a_ticket(): void
    {
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $otherUser = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $own = Ticket::factory()->create(['user_id' => $user->id]);
        $other = Ticket::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($this->policy->schedule($technician, $other));
        $this->assertTrue($this->policy->schedule($user, $own));
        $this->assertFalse($this->policy->schedule($user, $other));
    }

    #[Test]
    public function only_technician_or_admin_can_submit_budget(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $user = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::User->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->submitBudget($admin, $ticket));
        $this->assertTrue($this->policy->submitBudget($technician, $ticket));
        $this->assertFalse($this->policy->submitBudget($user, $ticket));
    }

    #[Test]
    public function only_technician_can_start_repair(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->startRepair($technician, $ticket));
        $this->assertFalse($this->policy->startRepair($admin, $ticket));
    }

    #[Test]
    public function only_assigned_technician_can_request_budget(): void
    {
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $otherTechnician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $assigned = Ticket::factory()->create(['assigned_to' => $technician->id]);
        $unassigned = Ticket::factory()->create();

        $this->assertTrue($this->policy->requestBudget($technician, $assigned));
        $this->assertFalse($this->policy->requestBudget($otherTechnician, $assigned));
        $this->assertFalse($this->policy->requestBudget($technician, $unassigned));
    }

    #[Test]
    public function only_admin_can_assign_analytics_and_preventive(): void
    {
        $admin = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->first()->id]);
        $technician = User::factory()->create(['profile_id' => UserProfile::where('name', UserRoleEnum::Technician->value)->first()->id]);
        $ticket = Ticket::factory()->create();

        $this->assertTrue($this->policy->assign($admin, $ticket));
        $this->assertFalse($this->policy->assign($technician, $ticket));
        $this->assertTrue($this->policy->viewAnalytics($admin));
        $this->assertFalse($this->policy->viewAnalytics($technician));
        $this->assertTrue($this->policy->exportAnalytics($admin));
        $this->assertFalse($this->policy->exportAnalytics($technician));
        $this->assertTrue($this->policy->createPreventive($admin));
        $this->assertFalse($this->policy->createPreventive($technician));
    }
}
