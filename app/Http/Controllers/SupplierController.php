<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateSupplierAction;
use App\Actions\UpdateSupplierAction;
use App\DTOs\StoreSupplierData;
use App\DTOs\UpdateSupplierData;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Support\SqlPattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class SupplierController extends Controller
{
    public function __construct(
        private readonly CreateSupplierAction $createSupplierAction,
        private readonly UpdateSupplierAction $updateSupplierAction,
    ) {}

    /**
     * Paginated listing of suppliers.
     */
    #[OA\Get(
        path: '/stock/suppliers',
        tags: ['Stock'],
        summary: 'List suppliers',
        description: 'Paginated list of suppliers, searchable by name or NIF.',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, description: 'Search by name or NIF', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of suppliers'),
            new OA\Response(response: 403, description: 'Access forbidden for the profile'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $query = Supplier::query();

        if ($request->filled('q')) {
            SqlPattern::whereContains($query, ['name', 'nif'], $request->string('q')->toString());
        }

        $suppliers = $query->orderBy('name')->paginate(15);

        return response()->json([
            'suppliers' => SupplierResource::collection($suppliers),
            'pagination' => [
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'total' => $suppliers->total(),
            ],
        ]);
    }

    /**
     * Supplier detail view.
     */
    #[OA\Get(
        path: '/stock/suppliers/{supplier}',
        tags: ['Stock'],
        summary: 'Supplier detail',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'supplier', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Supplier with associated parts'),
            new OA\Response(response: 404, description: 'Supplier not found'),
        ]
    )]
    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return response()->json([
            'supplier' => new SupplierResource($supplier->load('parts.taxRate')),
        ]);
    }

    /**
     * Creates a new supplier.
     */
    #[OA\Post(
        path: '/admin/suppliers',
        tags: ['Admin Stock'],
        summary: 'Create supplier',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'nif', type: 'string'),
                    new OA\Property(property: 'contact', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'address', type: 'string'),
                    new OA\Property(property: 'avg_lead_time_days', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Supplier created successfully'),
            new OA\Response(response: 422, description: 'Invalid data'),
        ]
    )]
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $data = StoreSupplierData::fromRequest($request->validated());
        $supplier = $this->createSupplierAction->execute($data);

        return response()->json([
            'message' => __('messages.Fornecedor criado com sucesso.'),
            'supplier' => new SupplierResource($supplier),
        ], 201);
    }

    /**
     * Updates a supplier.
     */
    #[OA\Patch(
        path: '/admin/suppliers/{supplier}',
        tags: ['Admin Stock'],
        summary: 'Update supplier',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'supplier', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'nif', type: 'string'),
                    new OA\Property(property: 'contact', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'address', type: 'string'),
                    new OA\Property(property: 'avg_lead_time_days', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Supplier updated successfully'),
            new OA\Response(response: 422, description: 'Invalid data'),
        ]
    )]
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $data = UpdateSupplierData::fromRequest($request->validated());
        $supplier = $this->updateSupplierAction->execute($supplier, $data);

        return response()->json([
            'message' => __('messages.Fornecedor atualizado com sucesso.'),
            'supplier' => new SupplierResource($supplier),
        ]);
    }

    /**
     * Soft-deletes a supplier.
     */
    #[OA\Delete(
        path: '/admin/suppliers/{supplier}',
        tags: ['Admin Stock'],
        summary: 'Delete supplier',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'supplier', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Supplier deleted successfully'),
            new OA\Response(response: 404, description: 'Supplier not found'),
        ]
    )]
    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return response()->json([
            'message' => __('messages.Fornecedor eliminado com sucesso.'),
        ]);
    }
}
