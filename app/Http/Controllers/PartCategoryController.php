<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PartCategoryActions;
use App\Http\Requests\StorePartCategoryRequest;
use App\Http\Requests\UpdatePartCategoryRequest;
use App\Http\Resources\PartCategoryResource;
use App\Models\PartCategory;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

final class PartCategoryController extends Controller
{
    public function __construct(
        private readonly PartCategoryActions $partCategoryActions,
    ) {}

    /**
     * Lists all part categories.
     */
    #[OA\Get(
        path: '/admin/part-categories',
        tags: ['Admin Stock'],
        summary: 'List part categories',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of part categories'),
        ]
    )]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', PartCategory::class);

        $categories = PartCategory::query()->orderBy('name')->get();

        return response()->json([
            'categories' => PartCategoryResource::collection($categories),
        ]);
    }

    /**
     * Creates a new category.
     */
    #[OA\Post(
        path: '/admin/part-categories',
        tags: ['Admin Stock'],
        summary: 'Create part category',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category created successfully'),
            new OA\Response(response: 422, description: 'Invalid data'),
        ]
    )]
    public function store(StorePartCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', PartCategory::class);

        $category = $this->partCategoryActions->create(
            name: $request->string('name')->toString(),
            active: $request->boolean('active', true),
        );

        return response()->json([
            'message' => __('messages.Categoria criada com sucesso.'),
            'category' => new PartCategoryResource($category),
        ], 201);
    }

    /**
     * Updates a category.
     */
    #[OA\Patch(
        path: '/admin/part-categories/{category}',
        tags: ['Admin Stock'],
        summary: 'Update part category',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Category updated successfully'),
            new OA\Response(response: 422, description: 'Invalid data'),
        ]
    )]
    public function update(UpdatePartCategoryRequest $request, PartCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $this->partCategoryActions->update(
            category: $category,
            name: $request->string('name')->toString(),
            active: $request->boolean('active', true),
        );

        return response()->json([
            'message' => __('messages.Categoria atualizada com sucesso.'),
            'category' => new PartCategoryResource($category),
        ]);
    }

    /**
     * Soft-deactivates a category.
     */
    #[OA\Delete(
        path: '/admin/part-categories/{category}',
        tags: ['Admin Stock'],
        summary: 'Deactivate part category',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category deactivated successfully'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function destroy(PartCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->update(['active' => false]);

        return response()->json([
            'message' => __('messages.Categoria desativada com sucesso.'),
        ]);
    }
}
