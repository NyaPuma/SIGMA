<?php

namespace App\Http\Controllers;

use App\Actions\CreateEquipmentAction;
use App\Actions\UpdateEquipmentAction;
use App\DTOs\StoreEquipmentData;
use App\DTOs\UpdateEquipmentData;
use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminEquipmentController extends Controller
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
        private readonly CreateEquipmentAction $createEquipmentAction,
        private readonly UpdateEquipmentAction $updateEquipmentAction,
    ) {}

    /**
     * Lists all equipment registered in the system.
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('viewAny', Equipment::class);

        // 2. Get filters from request
        $search = $request->query('q');
        $status = $request->query('status');
        $category = $request->query('category');

        $search = is_string($search) ? $search : null;
        $status = is_string($status) ? $status : null;
        $category = is_string($category) ? $category : null;

        // 3. Search for equipment with applied filters
        $equipments = $this->equipmentRepository->getAll(['room', 'category'], $search, $status, $category);

        return response()->json([
            'equipments' => EquipmentResource::collection($equipments),
        ]);
    }

    /**
     * Creates a new equipment in the system.
     */
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('create', Equipment::class);

        // 2. Execute DTO and Action to create the equipment
        $data = StoreEquipmentData::fromRequest($request->validated());
        $equipment = $this->createEquipmentAction->execute($data);

        $equipment->loadMissing('room');

        return response()->json([
            'message' => __('messages.Equipamento criado com sucesso.'),
            'equipment' => new EquipmentResource($equipment),
        ], 201);
    }

    /**
     * Updates an existing equipment's data.
     */
    public function update(UpdateEquipmentRequest $request, Equipment $equipment): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('update', $equipment);

        // 2. Execute DTO and Action for update
        $data = UpdateEquipmentData::fromRequest($request->validated());
        $updatedEquipment = $this->updateEquipmentAction->execute($equipment, $data);

        $updatedEquipment->loadMissing('room');

        return response()->json([
            'message' => __('messages.Equipamento atualizado com sucesso.'),
            'equipment' => new EquipmentResource($updatedEquipment),
        ]);
    }

    /**
     * Deletes an equipment from the system.
     */
    public function destroy(Request $request, Equipment $equipment): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('delete', $equipment);

        // 2. Execute removal via Repository
        $this->equipmentRepository->delete($equipment);

        return response()->json([
            'message' => __('messages.Equipamento eliminado com sucesso.'),
        ]);
    }
}
