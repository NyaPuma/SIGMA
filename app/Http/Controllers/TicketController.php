<?php

namespace App\Http\Controllers;

use App\Actions\CreateTicketAction;
use App\DTOs\CreateTicketData;
use App\DTOs\TicketFilters;
use App\Enums\TicketPriorityEnum;
use App\Enums\UserRoleEnum;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Jobs\GenerateAiRecommendationJob;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Services\TechnicianAssignmentService;
use App\Services\TicketSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepository,
        private readonly CreateTicketAction $createTicketAction,
        private readonly TechnicianAssignmentService $technicianService,
        private readonly TicketSearchService $searchService,
    ) {}

    /**
     * Lists all tickets registered in the system with the necessary relations.
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Authorization via Policy (ViewAny)
        $this->authorize('viewAny', Ticket::class);

        $user = $this->authenticatedUser($request);

        // Technicians only see the tickets assigned to them; admins see all.
        if ($user->isTechnician()) {
            $tickets = $this->ticketRepository->getTicketsByTechnician($user->id);
        } elseif ($user->isAdmin()) {
            $tickets = $this->ticketRepository->getAll(['equipment', 'room', 'user', 'technician', 'status']);
        } else {
            // Regular users only see their own tickets
            $tickets = $this->ticketRepository->getTicketsByUser($user->id);
        }

        return response()->json([
            'tickets' => TicketResource::collection($tickets),
        ]);
    }

    /**
     * Creates a new support ticket.
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        // 1. Authorization via Policy (Create)
        $this->authorize('create', Ticket::class);

        $user = $this->authenticatedUser($request);
        $data = CreateTicketData::fromRequest($request->validated());

        $ticket = $this->createTicketAction->execute($user, $data);

        // AI technician recommendation processed in the background
        // (GenerateAiRecommendationJob persists the result on the ticket itself).
        GenerateAiRecommendationJob::dispatch($ticket)->afterCommit();

        $ticket->loadMissing(['equipment', 'room', 'user', 'status']);

        return response()->json([
            'message' => __('messages.Ticket criado com sucesso.'),
            'ticket' => new TicketResource($ticket),
        ], 201);
    }

    /**
     * Searches and filters tickets based on the submitted criteria.
     */
    public function search(Request $request): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('viewAny', Ticket::class);

        $priority = $request->input('priority');
        if ($priority && ! in_array($priority, TicketPriorityEnum::acceptedValues(), true)) {
            return response()->json([
                'message' => __('common.Prioridade inválida. Valores válidos: baixa, média, alta, crítica.'),
            ], 422);
        }

        $filters = TicketFilters::fromRequest($request->all());

        // Regular users only search their own tickets; technicians only
        // search the tickets assigned to them.
        $user = $this->authenticatedUser($request);
        if ($user->isTechnician()) {
            $filters = new TicketFilters(
                query: $filters->query,
                priority: $filters->priority,
                status: $filters->status,
                dateFrom: $filters->dateFrom,
                dateTo: $filters->dateTo,
                userId: $filters->userId,
                technicianId: $user->id,
                equipmentId: $filters->equipmentId,
                roomId: $filters->roomId,
            );
        } elseif (! $user->isAdmin()) {
            $filters = new TicketFilters(
                query: $filters->query,
                priority: $filters->priority,
                status: $filters->status,
                dateFrom: $filters->dateFrom,
                dateTo: $filters->dateTo,
                userId: $user->id,
                technicianId: $filters->technicianId,
                equipmentId: $filters->equipmentId,
                roomId: $filters->roomId,
            );
        }

        $tickets = $this->searchService->search($filters);

        return response()->json([
            'tickets' => TicketResource::collection($tickets),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Displays the details of a specific ticket (supports JSON for API or View for Web Frontend).
     */
    public function show(Request $request, Ticket $ticket): JsonResponse|View
    {
        // 1. Authorization via Policy
        $this->authorize('view', $ticket);

        // 2. Load advanced relations if needed
        $ticket->loadMissing(['equipment.category', 'room', 'user', 'technician', 'status']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ticket' => new TicketResource($ticket),
            ]);
        }

        return view('ui.ticket-detail', compact('ticket'));
    }

    /**
     * Lists all tickets that are currently open in the system.
     */
    public function openTickets(Request $request): JsonResponse
    {
        // 1. Authorization via Policy (ViewAny restricted to technicians/admins)
        $this->authorize('viewAny', Ticket::class);

        $user = $this->authenticatedUser($request);
        if (! $user->profile || ! in_array($user->profile->name, [UserRoleEnum::Technician->value, UserRoleEnum::Admin->value], true)) {
            return response()->json(['message' => __('common.Acesso proibido para o seu perfil.')], 403);
        }

        $tickets = $this->ticketRepository->getOpenTickets();

        return response()->json([
            'tickets' => TicketResource::collection($tickets),
        ]);
    }

    /**
     * Returns the most urgent open ticket for priority assignment.
     */
    public function getMostUrgentOpenTicket(Request $request): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('viewAny', Ticket::class);

        // Restricted to technicians/admins (sensitive operational data)
        $user = $this->authenticatedUser($request);
        if (! $user->profile || ! in_array($user->profile->name, [UserRoleEnum::Technician->value, UserRoleEnum::Admin->value], true)) {
            return response()->json(['message' => __('common.Acesso proibido para o seu perfil.')], 403);
        }

        $excludeId = (int) $request->input('exclude', 0);
        $ticket = $this->technicianService->findMostUrgentOpenTicket($excludeId > 0 ? $excludeId : null);

        if (! $ticket) {
            return response()->json([
                'ticket_id' => null,
                'message' => __('tickets.Não existem tickets abertos prioritários.'),
            ], 404);
        }

        return response()->json([
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'priority' => $ticket->priority,
        ]);
    }
}
