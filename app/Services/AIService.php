<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use OpenAI\Laravel\Facades\OpenAI;

final class AIService
{
    public function __construct(
        private readonly TicketStatusService $statusService,
        private readonly ?CircuitBreaker $circuitBreaker = null,
        private readonly ?FeatureFlagService $featureFlags = null,
    ) {}

    /**
     * Recommends the most qualified technician using AI for ticket resolution.
     *
     * @return array{technician_id: int|null, justification: string}
     */
    public function recommendTechnician(Ticket $ticket): array
    {
        $featureFlags = $this->featureFlags ?? new FeatureFlagService;

        if (! $featureFlags->enabled('ai_recommendations')) {
            return [
                'technician_id' => null,
                'justification' => 'AI recommendations are currently disabled. Please select a technician manually through the Assignment Panel.',
            ];
        }

        $technicians = User::whereHas('profile', fn ($q) => $q->where('name', UserRoleEnum::Technician->value))
            ->where('active', true)
            ->withCount(['assignedTickets as active_tickets' => function (Builder $query) {
                $closedStatusId = $this->statusService->getByName(TicketStatusEnum::Closed);
                $cancelledStatusId = $this->statusService->getByName(TicketStatusEnum::Cancelled);
                $statusIds = array_filter([$closedStatusId, $cancelledStatusId]);

                if (! empty($statusIds)) {
                    $query->whereNotIn('status_id', $statusIds);
                }
            }])
            ->get(['id', 'name']);

        // Immediate fallback if no operational team is available
        if ($technicians->isEmpty()) {
            return [
                'technician_id' => null,
                'justification' => 'No active operational technicians available for automatic allocation at this time.',
            ];
        }

        // Specialties mapped statically via code to enrich business context
        $specialties = [
            1 => 'Electrical and Automation',
            2 => 'Mechanical and Hydraulic',
            3 => 'IT Systems and Networks',
        ];

        // Prompt engineering focused on Administrator Decision Profile
        $prompt = "You act as an Industrial Maintenance Engineering Consultant for the system Administrator.\n";
        $prompt .= "Your sole role is to analyze the fault ticket and recommend the most qualified technician.\n\n";

        $prompt .= "--- TICKET UNDER ANALYSIS ---\n";
        $prompt .= '- Problem Description: '.$ticket->description."\n";
        $prompt .= '- Equipment: '.($ticket->equipment->name ?? 'Not Specified')."\n";
        $prompt .= '- Technical Category: '.($ticket->equipment->category->name ?? 'General')."\n\n";

        $prompt .= "--- AVAILABLE HUMAN RESOURCES ---\n";
        foreach ($technicians as $technician) {
            $specialty = $specialties[($technician->id % 3) + 1];
            $prompt .= "- ID: {$technician->id} | Name: {$technician->name} | Specialty: {$specialty} | Current Workload: {$technician->active_tickets} tickets\n";
        }

        $prompt .= "\n--- SELECTION CRITERIA ---\n";
        $prompt .= "1. Find affinity between the problem Category and the technician's Specialty.\n";
        $prompt .= "2. Choose the least overloaded technician (lowest Current Workload) for team balancing.\n";
        $prompt .= "3. Respond strictly with the clean JSON object below, no markdown (```json), no introductions or observations.\n\n";

        $prompt .= "--- REQUIRED RESPONSE FORMAT ---\n";
        $prompt .= "{\n";
        $prompt .= '  "technician_id": <insert_numeric_id_only>,'."\n";
        $prompt .= '  "justification": "<a short professional sentence in English validating the choice for the Operations Director>"'."\n";
        $prompt .= '}';

        try {
            $breaker = $this->circuitBreaker ?? new CircuitBreaker;
            $response = $breaker->run('openai', function () use ($prompt) {
                return OpenAI::chat()->create([
                    'model' => config('services.custom.ai.model'),
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => is_numeric(config('services.custom.ai.temperature', 0.2)) ? (float) config('services.custom.ai.temperature', 0.2) : 0.2,
                ]);
            });

            if ($response === null) {
                throw new Exception('AI dependency circuit is open.');
            }

            $content = trim($response->choices[0]->message->content ?? '');

            // Defensive cleanup in case AI adds markdown blocks to JSON response
            $content = preg_replace('/^```json\s*([\s\S]*?)\s*```$/i', '$1', $content) ?? $content;
            $content = preg_replace('/^```\s*([\s\S]*?)\s*```$/i', '$1', $content) ?? $content;

            $result = json_decode(trim($content), true);

            if (is_array($result) && isset($result['technician_id'])) {
                return [
                    'technician_id' => is_numeric($result['technician_id'] ?? null) ? (int) $result['technician_id'] : null,
                    'justification' => is_string($result['justification'] ?? null) ? (string) $result['justification'] : 'Assignment recommended by AI assistant.',
                ];
            }

            throw new Exception('Malformed JSON or invalid structure.');
        } catch (Exception $e) {
            return [
                'technician_id' => null,
                'justification' => 'AI assistant unavailable. Please select a technician manually through the Assignment Panel.',
            ];
        }
    }
}
