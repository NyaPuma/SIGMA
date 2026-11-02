<?php

namespace App\Console\Commands;

use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Equipment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketStatusService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Telemetry simulation command for preventive maintenance.
 * Generates automatic fault tickets based on random equipment anomalies.
 */
class SimulateTelemetry extends Command
{
    protected $signature = 'telemetry:simulate
                            {--equipments=3 : Maximum number of equipment to check per run}
                            {--probability=30 : Probability percentage of an anomaly (0-100)}
                            {--dry-run : Runs the simulation without persisting tickets to the database}';

    protected $description = 'Simulates equipment telemetry and automatically generates preventive-maintenance tickets when anomalies are detected.';

    /** @var list<array{title: string, description: string, priority: string}> */
    private array $anomalyTypes = [
        [
            'title' => 'Temperature above operational limit',
            'description' => 'The equipment temperature sensor recorded values above 85°C over a prolonged period. Inspection of the cooling system is recommended.',
            'priority' => TicketPriorityEnum::High->value,
        ],
        [
            'title' => 'Abnormal vibration detected',
            'description' => 'The accelerometer recorded vibration patterns outside normal parameters. This may indicate bearing wear or mechanical misalignment.',
            'priority' => TicketPriorityEnum::Medium->value,
        ],
        [
            'title' => 'High energy consumption',
            'description' => 'The monitoring system recorded electrical consumption 40% above expected over the last 6 hours. Possible motor fault or overheating.',
            'priority' => TicketPriorityEnum::Medium->value,
        ],
        [
            'title' => 'Pressure outside safety limits',
            'description' => 'The pressure sensor reported anomalous values. Immediate verification is required to avoid operational risks.',
            'priority' => TicketPriorityEnum::High->value,
        ],
        [
            'title' => 'Scheduled preventive maintenance alert',
            'description' => 'The equipment has reached the manufacturer-recommended preventive-maintenance interval (500 operating hours). Perform routine inspection.',
            'priority' => TicketPriorityEnum::Low->value,
        ],
    ];

    public function handle(TicketStatusService $statusService): int
    {
        $maxEquipments = (int) $this->option('equipments');
        $probability = (int) $this->option('probability');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting telemetry simulation...');

        // Find the system administrator user
        $systemUser = User::whereHas('profile', fn ($q) => $q->where('name', UserRoleEnum::Admin->value))->first();

        if (! $systemUser) {
            $this->error('No administrator user found to attribute as the ticket author.');

            return self::FAILURE;
        }

        // Pre-fetch the Open status ID (resolves N+1)
        $openStatusId = $statusService->getByName(TicketStatusEnum::Open);

        // Load active equipment with eager-loaded unresolved tickets to avoid N+1
        $hasOpenTicket = function (Builder $query) use ($openStatusId): void {
            // Considers it open if status is Open or active non-completed states
            $query->where('status_id', $openStatusId);
        };

        $equipments = Equipment::where('active', true)
            ->withExists(['tickets as has_open_ticket' => $hasOpenTicket])
            ->inRandomOrder()
            ->limit($maxEquipments)
            ->get();

        if ($equipments->isEmpty()) {
            $this->warn('No active equipment found in the database.');

            return self::SUCCESS;
        }

        $ticketsCreated = 0;

        foreach ($equipments as $equipment) {
            // Avoids duplication by consulting the pre-loaded Eloquent attribute
            if ((bool) $equipment->getAttribute('has_open_ticket')) {
                $this->line("  ⏭  Equipment #{$equipment->id} ({$equipment->name}) already has an active ticket. Skipping.");

                continue;
            }

            // Anomaly probability test (0 to 100)
            if (random_int(1, 100) > $probability) {
                $this->line("  Equipment #{$equipment->id} ({$equipment->name}) without detected anomalies.");

                continue;
            }

            $anomaly = $this->anomalyTypes[random_int(0, count($this->anomalyTypes) - 1)];

            if ($dryRun) {
                $this->info("  [DRY-RUN] Would create Ticket for equip. #{$equipment->id} ({$equipment->name}): {$anomaly['title']}");
                $ticketsCreated++;

                continue;
            }

            DB::transaction(function () use ($systemUser, $equipment, $anomaly, $openStatusId, &$ticketsCreated) {
                $ticket = Ticket::create([
                    'user_id' => $systemUser->id,
                    'equipment_id' => $equipment->id,
                    'room_id' => $equipment->room_id ?? null,
                    'title' => "[TELEMETRIA] {$anomaly['title']} — {$equipment->name}",
                    'description' => $anomaly['description']."\n\n".
                                     "Equipment: {$equipment->name}\n".
                                     "Equipment ID: #{$equipment->id}\n".
                                     'Anomaly date: '.now()->format('d/m/Y H:i:s')."\n".
                                     'Automatically generated by the telemetry system.',
                    'priority' => $anomaly['priority'],
                    'status_id' => $openStatusId,
                    'opened_at' => now(),
                ]);

                $ticketsCreated++;
                $this->info("  Ticket #{$ticket->id} created for equip. #{$equipment->id} ({$equipment->name}): {$anomaly['title']}");
            });
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("Simulation completed. {$prefix}{$ticketsCreated} maintenance ticket(s) generated.");

        return self::SUCCESS;
    }
}
