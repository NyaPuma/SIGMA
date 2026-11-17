<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Jobs\SendTestEmailJob;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

final class NotificationController extends Controller
{
    /**
     * Lists the authenticated user's notifications in a paginated manner.
     */
    #[OA\Get(
        path: '/notifications',
        tags: ['Notifications'],
        summary: 'List user notifications',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of notifications'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $requestedPerPage = (int) $request->query('per_page', 50);
        $perPage = $requestedPerPage >= 1 ? min($requestedPerPage, 200) : 50;

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'notifications' => NotificationResource::collection($notifications)->response()->getData(true),
        ]);
    }

    /**
     * Marks a specific notification as read.
     */
    #[OA\Patch(
        path: '/notifications/{id}',
        tags: ['Notifications'],
        summary: 'Mark notification as read',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification updated'),
            new OA\Response(response: 404, description: 'Notification not found'),
        ]
    )]
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        // Ensures the notification strictly belongs to the authenticated user
        $notification = Notification::where('user_id', $user->id)->find($id);

        if (! $notification) {
            return response()->json([
                'message' => __('common.Notificação não encontrada.'),
            ], 404);
        }

        $notification->is_read = true;
        $notification->save();

        return response()->json([
            'message' => __('messages.Notificação marcada como lida com sucesso.'),
            'notification' => new NotificationResource($notification),
        ]);
    }

    /**
     * Dispatches a test email in the background via queue.
     */
    #[OA\Post(
        path: '/notifications/test-email',
        tags: ['Notifications'],
        summary: 'Send a test email through the configured mailer',
        security: [['X-Auth-Token' => []], ['BearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Test email sent'),
        ]
    )]
    public function sendTestEmail(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        SendTestEmailJob::dispatch($user->email, $user->name);

        Log::info('Test email queued', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => __('common.Email de teste em processamento via fila.'),
        ]);
    }
}
