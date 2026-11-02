<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\TaxRateActions;
use App\Http\Requests\StoreTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;
use App\Http\Resources\TaxRateResource;
use App\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

final class TaxRateController extends Controller
{
    public function __construct(
        private readonly TaxRateActions $taxRateActions,
    ) {}

    /**
     * Lista todas as taxas de IVA.
     */
    #[OA\Get(
        path: '/admin/tax-rates',
        tags: ['Admin Stock'],
        summary: 'List VAT rates',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of VAT rates'),
        ]
    )]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', TaxRate::class);

        $taxRates = TaxRate::query()->orderBy('percent')->get();

        return response()->json([
            'tax_rates' => TaxRateResource::collection($taxRates),
        ]);
    }

    /**
     * Creates a new VAT rate.
     */
    #[OA\Post(
        path: '/admin/tax-rates',
        tags: ['Admin Stock'],
        summary: 'Create VAT rate',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'percent'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'percent', type: 'number'),
                    new OA\Property(property: 'is_default', type: 'boolean'),
                    new OA\Property(property: 'active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'VAT rate created successfully'),
            new OA\Response(response: 422, description: 'Invalid data'),
        ]
    )]
    public function store(StoreTaxRateRequest $request): JsonResponse
    {
        $this->authorize('create', TaxRate::class);

        $taxRate = $this->taxRateActions->create(
            name: $request->string('name')->toString(),
            percent: $request->float('percent'),
            isDefault: $request->boolean('is_default', false),
            active: $request->boolean('active', true),
        );

        return response()->json([
            'message' => __('messages.Taxa de IVA criada com sucesso.'),
            'tax_rate' => new TaxRateResource($taxRate),
        ], 201);
    }

    /**
     * Updates a VAT rate.
     */
    #[OA\Patch(
        path: '/admin/tax-rates/{taxRate}',
        tags: ['Admin Stock'],
        summary: 'Update VAT rate',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'taxRate', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'percent'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'percent', type: 'number'),
                    new OA\Property(property: 'is_default', type: 'boolean'),
                    new OA\Property(property: 'active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'VAT rate updated successfully'),
            new OA\Response(response: 422, description: 'Invalid data'),
        ]
    )]
    public function update(UpdateTaxRateRequest $request, TaxRate $taxRate): JsonResponse
    {
        $this->authorize('update', $taxRate);

        $taxRate = $this->taxRateActions->update(
            taxRate: $taxRate,
            name: $request->string('name')->toString(),
            percent: $request->float('percent'),
            isDefault: $request->boolean('is_default', false),
            active: $request->boolean('active', true),
        );

        return response()->json([
            'message' => __('messages.Taxa de IVA atualizada com sucesso.'),
            'tax_rate' => new TaxRateResource($taxRate),
        ]);
    }

    /**
     * Soft-deactivates a VAT rate.
     */
    #[OA\Delete(
        path: '/admin/tax-rates/{taxRate}',
        tags: ['Admin Stock'],
        summary: 'Deactivate VAT rate',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'taxRate', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'VAT rate deactivated successfully'),
            new OA\Response(response: 404, description: 'VAT rate not found'),
        ]
    )]
    public function destroy(TaxRate $taxRate): JsonResponse
    {
        $this->authorize('delete', $taxRate);

        $taxRate->update(['active' => false]);

        return response()->json([
            'message' => __('messages.Taxa de IVA desativada com sucesso.'),
        ]);
    }
}
