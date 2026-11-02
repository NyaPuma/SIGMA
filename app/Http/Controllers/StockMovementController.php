<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StockMovementTypeEnum;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Part;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

final class StockMovementController extends Controller
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    /**
     * Paginated list of movements, with filters by part and type.
     */
    #[OA\Get(
        path: '/stock/movements',
        tags: ['Stock'],
        summary: 'List stock movements',
        description: 'Paginated list of movements, filterable by part, movement type, and ticket.',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'part_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'movement_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['in', 'out', 'adjust', 'return'])),
            new OA\Parameter(name: 'ticket_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of movements'),
            new OA\Response(response: 403, description: 'Access forbidden for the profile'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockMovement::class);

        $query = StockMovement::query()->with(['part.taxRate', 'user']);

        if ($request->filled('part_id')) {
            $query->where('part_id', (int) $request->query('part_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', (string) $request->query('movement_type'));
        }

        if ($request->filled('ticket_id')) {
            $query->where('ticket_id', (int) $request->query('ticket_id'));
        }

        $movements = $query->latest()->paginate(20);

        return response()->json([
            'movements' => StockMovementResource::collection($movements),
            'pagination' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * Registers a stock movement (in/out/adjust/return).
     */
    #[OA\Post(
        path: '/stock/movements',
        tags: ['Stock'],
        summary: 'Register stock movement',
        description: 'Registers a movement and atomically updates the part stock. Outflows use positive quantity.',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['part_id', 'movement_type', 'quantity'],
                properties: [
                    new OA\Property(property: 'part_id', type: 'integer'),
                    new OA\Property(property: 'movement_type', type: 'string', enum: ['in', 'out', 'adjust', 'return']),
                    new OA\Property(property: 'quantity', type: 'integer'),
                    new OA\Property(property: 'reason', type: 'string'),
                    new OA\Property(property: 'ticket_id', type: 'integer'),
                    new OA\Property(property: 'equipment_id', type: 'integer'),
                    new OA\Property(property: 'unit_price_snapshot', type: 'number'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Movement recorded successfully'),
            new OA\Response(response: 422, description: 'Invalid data or insufficient stock'),
            new OA\Response(response: 500, description: 'Could not record the movement'),
        ]
    )]
    public function store(StoreStockMovementRequest $request): JsonResponse
    {
        $this->authorize('create', StockMovement::class);

        /** @var array{part_id: int|string, movement_type: string, quantity: int|string, reason?: string|null, ticket_id?: int|string|null, equipment_id?: int|string|null, unit_price_snapshot?: int|float|string|null} $validated */
        $validated = $request->validated();

        $part = Part::query()->findOrFail((int) $validated['part_id']);

        $movementType = StockMovementTypeEnum::normalize($validated['movement_type'])
            ?? StockMovementTypeEnum::Adjust;

        try {
            $movement = $this->stockMovementService->record(
                part: $part,
                movementType: $movementType,
                quantity: (int) $validated['quantity'],
                reason: $validated['reason'] ?? null,
                ticketId: isset($validated['ticket_id']) ? (int) $validated['ticket_id'] : null,
                equipmentId: isset($validated['equipment_id']) ? (int) $validated['equipment_id'] : null,
                user: $request->user(),
                unitPriceSnapshot: isset($validated['unit_price_snapshot']) ? (float) $validated['unit_price_snapshot'] : null,
            );
            Cache::forget('stock_dashboard_summary');
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['quantity' => [$e->getMessage()]],
            ], 422);
        } catch (Throwable $e) {
            Log::error('Failed to record stock movement', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('common.Não foi possível registar o movimento.'),
            ], 500);
        }

        return response()->json([
            'message' => __('messages.Movimento de stock registado com sucesso.'),
            'movement' => new StockMovementResource($movement->load(['part.taxRate', 'user'])),
        ], 201);
    }
}
