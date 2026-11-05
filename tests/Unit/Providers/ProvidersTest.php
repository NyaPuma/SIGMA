<?php

namespace Tests\Unit\Providers;

use App\Events\TicketCreated;
use App\Events\TicketStatusChanged;
use App\Events\TicketStatusUpdated;
use App\Models\Audit;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserProfile;
use App\Policies\AuditPolicy;
use App\Policies\EquipmentPolicy;
use App\Policies\RoomPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use App\Policies\UserProfilePolicy;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EquipmentRepository;
use App\Repositories\RoomRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;
use App\Services\AIService;
use App\Services\AnalyticsService;
use App\Services\NotificationService;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\Gate;
use Tests\Base\DatabaseTestCase;

class ProvidersTest extends DatabaseTestCase
{
    public function test_repository_interfaces_resolve_to_concrete_implementations(): void
    {
        $this->assertInstanceOf(UserRepository::class, app(UserRepositoryInterface::class));
        $this->assertInstanceOf(TicketRepository::class, app(TicketRepositoryInterface::class));
        $this->assertInstanceOf(EquipmentRepository::class, app(EquipmentRepositoryInterface::class));
        $this->assertInstanceOf(RoomRepository::class, app(RoomRepositoryInterface::class));
    }

    public function test_domain_services_are_registered_as_singletons(): void
    {
        $this->assertSame(app(TicketStatusService::class), app(TicketStatusService::class));
        $this->assertSame(app(AnalyticsService::class), app(AnalyticsService::class));
        $this->assertSame(app(NotificationService::class), app(NotificationService::class));
        $this->assertSame(app(AIService::class), app(AIService::class));
    }

    public function test_policies_are_registered_for_all_models(): void
    {
        $this->assertInstanceOf(TicketPolicy::class, Gate::getPolicyFor(Ticket::class));
        $this->assertInstanceOf(UserPolicy::class, Gate::getPolicyFor(User::class));
        $this->assertInstanceOf(EquipmentPolicy::class, Gate::getPolicyFor(Equipment::class));
        $this->assertInstanceOf(RoomPolicy::class, Gate::getPolicyFor(Room::class));
        $this->assertInstanceOf(UserProfilePolicy::class, Gate::getPolicyFor(UserProfile::class));
    }

    public function test_audit_policy_is_resolved_by_auto_discovery(): void
    {
        $this->assertInstanceOf(AuditPolicy::class, Gate::getPolicyFor(Audit::class));
    }

    public function test_events_mapping_registers_listeners(): void
    {
        $events = app('events');

        $this->assertTrue($events->hasListeners(TicketCreated::class));
        $this->assertTrue($events->hasListeners(TicketStatusChanged::class));
        $this->assertTrue($events->hasListeners(TicketStatusUpdated::class));
    }
}
