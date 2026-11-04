<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\TicketCreated;
use App\Events\TicketStatusChanged;
use App\Events\TicketStatusUpdatedBroadcast;
use App\Listeners\LogTicketWorkflowChange;
use App\Listeners\NotifyAssignedTechnician;
use App\Listeners\SendTicketCreatedNotification;
use App\Listeners\SendTicketStatusNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TicketCreated::class => [
            SendTicketCreatedNotification::class,
        ],
        TicketStatusChanged::class => [
            LogTicketWorkflowChange::class,
        ],
        TicketStatusUpdatedBroadcast::class => [
            SendTicketStatusNotification::class,
            NotifyAssignedTechnician::class,
        ],
    ];

    /**
     * Registers any events for the application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determines if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // Keeps explicit mapping for performance and control reasons
    }
}
