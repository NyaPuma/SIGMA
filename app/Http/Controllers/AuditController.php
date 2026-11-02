<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditResource;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class AuditController extends Controller
{
    /**
     * Lists the system's audit records.
     * Protected globally via middleware and verified via Policy.
     */
    #[OA\Get(
        path: '/admin/audits',
        tags: ['Admin'],
        summary: 'List audits',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of audit records'
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('viewAny', Audit::class);

        // 2. Paginated search with Eager Loading of the user relation
        $perPageRaw = config('services.custom.pagination.admin_per_page', 15);
        $perPage = is_numeric($perPageRaw) ? (int) $perPageRaw : 15;

        $audits = Audit::with('user')
            ->latest()
            ->paginate($perPage);

        // 3. Standardized response using API Resource
        return response()->json([
            'audits' => AuditResource::collection($audits)->response()->getData(true),
        ]);
    }
}
