<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Http\Resources\TicketResource;
use App\Models\Audit;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EquipmentService;
use App\Services\ThemePresetService;
use App\Services\TicketStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UiController extends Controller
{
    public function __construct(
        private readonly EquipmentService $equipmentService,
        private readonly ThemePresetService $themePresets,
    ) {}

    /**
     * Renders the main page (dashboard) of the application.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('ui.index', ['user' => $user]);
    }

    /**
     * Renders the tickets management view.
     */
    public function tickets(Request $request): View
    {
        $user = $request->user();

        return view('ui.tickets', ['user' => $user]);
    }

    /**
     * Renders the ticket creation form.
     */
    public function ticketCreate(Request $request): View
    {
        $user = $request->user();

        return view('ui.ticket-create', ['user' => $user]);
    }

    /**
     * Renders the equipment management view.
     */
    public function equipments(Request $request): View
    {
        $user = $request->user();

        return view('ui.equipments', ['user' => $user]);
    }

    /**
     * Renders the equipment creation form.
     */
    public function equipmentCreate(Request $request): View
    {
        $user = $request->user();

        return view('ui.equipments.create', [
            'user' => $user,
            'rooms' => Room::query()->orderBy('name')->get(),
            'categories' => EquipmentCategory::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Renders the edit form for a specific piece of equipment.
     */
    public function equipmentEdit(Request $request, Equipment $equipment): View
    {
        $user = $request->user();

        return view('ui.equipments.edit', [
            'user' => $user,
            'equipment' => $equipment,
            'rooms' => Room::query()->orderBy('name')->get(),
            'categories' => EquipmentCategory::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Renders the detail view for a specific piece of equipment.
     */
    public function equipmentDetail(Request $request, Equipment $equipment): View
    {
        $user = $request->user();

        // A página mostra os tickets do equipamento e o trilho de auditoria,
        // por isso a EquipmentPolicy tem de ser aplicada aqui: as rotas /ui/*
        // não levam middleware de papel e, sem esta chamada, qualquer
        // operador percorria os ids para ler dados de outros utilizadores.
        $this->authorize('view', $equipment);

        // 1. Relations loaded eagerly for the detail page
        $equipment->loadMissing([
            'room',
            'category',
            'tickets.status',
            'tickets.user',
            'tickets.technician',
        ]);

        // 2. Quick equipment statistics
        $statusService = app(TicketStatusService::class);
        $openStatusId = $statusService->getByName(TicketStatusEnum::Open);
        $inProgressStatusId = $statusService->getByName(TicketStatusEnum::InProgress);

        $tickets = $equipment->tickets->sortByDesc('opened_at');

        // 3. Recent audit trail for this equipment
        $audits = Audit::query()
            ->where('auditable_type', Equipment::class)
            ->where('auditable_id', $equipment->id)
            ->with('user')
            ->latest()
            ->limit(12)
            ->get();

        return view('ui.equipments.show', [
            'user' => $user,
            'equipment' => $equipment,
            'tickets' => $tickets,
            'audits' => $audits,
            'openTicketsCount' => $tickets->where('status_id', $openStatusId)->count(),
            'inProgressTicketsCount' => $tickets->where('status_id', $inProgressStatusId)->count(),
        ]);
    }

    /**
     * Renders the user management view.
     */
    public function users(Request $request): View
    {
        $user = $request->user();

        return view('ui.users', ['user' => $user]);
    }

    /**
     * Renders the user creation form.
     */
    public function userCreate(Request $request): View
    {
        $user = $request->user();

        return view('ui.users-create', ['user' => $user]);
    }

    /**
     * Renders the edit form for a specific user.
     */
    public function userEdit(Request $request, User $targetUser): View
    {
        $user = $request->user();
        $targetUser->loadMissing('profile');

        return view('ui.users-edit', [
            'user' => $user,
            'targetUser' => $targetUser,
        ]);
    }

    /**
     * Renders the detail view for a specific user.
     */
    public function userDetail(Request $request, User $targetUser): View
    {
        $user = $request->user();

        $targetUser->loadMissing('profile');

        return view('ui.users.show', [
            'user' => $user,
            'targetUser' => $targetUser,
        ]);
    }

    /**
     * Renders the room management view.
     */
    public function rooms(Request $request): View
    {
        $user = $request->user();

        return view('ui.rooms', ['user' => $user]);
    }

    /**
     * Renders the room creation form.
     */
    public function roomCreate(Request $request): View
    {
        $user = $request->user();

        return view('ui.rooms.create', ['user' => $user]);
    }

    /**
     * Renders the detail view for a specific room.
     */
    public function roomDetail(Request $request, Room $room): View
    {
        $user = $request->user();

        // Ver nota em equipmentDetail: a página expõe tickets e auditoria da sala.
        $this->authorize('view', $room);

        // 1. Relations loaded eagerly for the detail page
        $room->loadMissing([
            'equipments.category',
            'tickets.status',
            'tickets.user',
            'tickets.technician',
        ]);

        // 2. Quick room statistics
        $statusService = app(TicketStatusService::class);
        $openStatusId = $statusService->getByName(TicketStatusEnum::Open);
        $inProgressStatusId = $statusService->getByName(TicketStatusEnum::InProgress);

        $tickets = $room->tickets->sortByDesc('opened_at');
        $equipments = $room->equipments->sortByDesc('active')->sortByDesc('created_at');

        // 3. Recent audit trail for this room
        $audits = Audit::query()
            ->where('auditable_type', Room::class)
            ->where('auditable_id', $room->id)
            ->with('user')
            ->latest()
            ->limit(12)
            ->get();

        return view('ui.rooms.show', [
            'room' => $room,
            'user' => $user,
            'equipments' => $equipments,
            'tickets' => $tickets,
            'audits' => $audits,
            'openTicketsCount' => $tickets->where('status_id', $openStatusId)->count(),
            'inProgressTicketsCount' => $tickets->where('status_id', $inProgressStatusId)->count(),
        ]);
    }

    /**
     * Renders the edit form for a specific room.
     */
    public function roomEdit(Request $request, Room $room): View
    {
        $user = $request->user();

        return view('ui.rooms.edit', [
            'room' => $room,
            'user' => $user,
        ]);
    }

    /**
     * Renders the system audit log view.
     */
    public function audits(Request $request): View
    {
        $user = $request->user();

        return view('ui.audits', ['user' => $user]);
    }

    /**
     * Renders the detail view for a specific ticket.
     */
    public function ticketDetail(Request $request, int $id): View
    {
        $user = $request->user();

        return view('ui.ticket-detail', [
            'ticketId' => $id,
            'user' => $user,
        ]);
    }

    /**
     * Helper JSON endpoint for paginated equipment listing in the UI.
     */
    public function getEquipments(Request $request): JsonResponse
    {
        $equipments = $this->equipmentService->listPaginated(
            $request->query('q'),
            $request->query('status'),
        );

        // O endpoint serve também o selector de equipamento do reporte de
        // avaria, que um operador usa; serial, asset_tag e notas são dados de
        // inventário que a EquipmentPolicy reserva a admin/técnico, por isso
        // ficam escondidos para os restantes papéis.
        $user = $request->user();

        if ($user !== null && ! $user->isAdmin() && ! $user->isTechnician()) {
            foreach ($equipments->items() as $equipment) {
                $equipment->makeHidden([
                    'asset_tag',
                    'serial',
                    'brand',
                    'model',
                    'manufacturer',
                    'purchase_date',
                    'warranty_until',
                    'notes',
                ]);
            }
        }

        return response()->json([
            'equipments' => $equipments,
        ]);
    }

    /**
     * Returns the active technical picket: active technicians with the number
     * of tickets currently "in progress" assigned to each one.
     */
    public function getTechnicalPicket(Request $request): JsonResponse
    {
        $picket = User::query()
            ->whereHas('profile', fn ($q) => $q->where('name', UserRoleEnum::Technician->value))
            ->where('active', true)
            ->withCount([
                'assignedTickets as in_progress_tickets' => fn (Builder $q) => $q
                    ->whereHas('status', fn (Builder $s) => $s->where('name', TicketStatusEnum::InProgress->value)),
            ])
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'in_progress_tickets' => $user->in_progress_tickets,
            ])
            ->values();

        return response()->json([
            'picket' => $picket,
        ]);
    }

    /**
     * Returns all active (non-final) tickets for the dashboard's
     * "Últimas Ocorrências Registadas" list: open, in progress and pending budget.
     */
    public function getActiveTickets(Request $request): JsonResponse
    {
        $statusService = app(TicketStatusService::class);

        $statusIds = [
            $statusService->getByName(TicketStatusEnum::Open),
            $statusService->getByName(TicketStatusEnum::InProgress),
            $statusService->getByName(TicketStatusEnum::PendingBudget),
        ];

        $query = Ticket::with(['equipment', 'room', 'user', 'status', 'technician'])
            ->whereIn('status_id', $statusIds);

        // Mesmo escopo de TicketController::index: este endpoint alimenta a
        // tabela "Últimas Ocorrências" do painel e não pode devolver a pool
        // inteira a quem só tem direitos sobre os próprios tickets.
        $user = $request->user();

        if ($user !== null && $user->isTechnician()) {
            $query->where('assigned_to', $user->id);
        } elseif ($user !== null && ! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return response()->json([
            'tickets' => TicketResource::collection($query->latest()->get()),
        ]);
    }

    /**
     * Renders the reports and analytics view.
     */
    public function analytics(Request $request): View
    {
        $user = $request->user();

        return view('ui.analytics', ['user' => $user]);
    }

    /**
     * Renders the authenticated user's profile view.
     */
    public function profile(Request $request): View
    {
        $user = $request->user();

        return view('ui.profile', ['user' => $user]);
    }

    /**
     * Renders the panel appearance settings page. Theme preference is
     * per-user: the page shows the presets and highlights the one currently
     * saved for the authenticated user.
     */
    public function themeAppearance(Request $request): View
    {
        $user = $this->authenticatedUser($request);

        return view('ui.settings.appearance', [
            'user' => $user,
            'presets' => $this->themePresets->all(),
            'activeTheme' => $this->themePresets->active($user->theme),
        ]);
    }

    /**
     * Saves the user's chosen theme preset (per-user preference).
     *
     * The appearance colour editor was removed — only predefined presets are
     * selectable, each one persisted on the authenticated user's row.
     */
    public function themeAppearanceUpdate(Request $request): JsonResponse|RedirectResponse
    {
        /** @var array{theme: string} $validated */
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(array_keys($this->themePresets->all()))],
        ]);

        $user = $this->authenticatedUser($request);
        $preset = $this->themePresets->applyForUser($user, $validated['theme']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'theme' => $validated['theme'],
                'mode' => $preset['mode'],
            ]);
        }

        return redirect()->route('ui.settings.appearance')
            ->with('status', __('messages.Aparência atualizada com sucesso.'));
    }
}
