<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TicketCommentController extends Controller
{
    /**
     * Adds a new comment to a specific ticket.
     */
    public function store(StoreCommentRequest $request, int $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);

        // 1. Authorization via Policy
        $this->authorize('view', $ticket);

        // 2. Register the comment with validated data
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->authenticatedUser($request)->id,
            'comment' => $request->validated('comment'),
        ]);

        $comment->loadMissing('user');

        return response()->json([
            'message' => __('messages.Comentário adicionado com sucesso.'),
            'comment' => new TicketCommentResource($comment),
        ], 201);
    }

    /**
     * Lists all comments associated with a ticket.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('view', $ticket);

        // 2. Load comments chronologically with the respective user relation
        $comments = TicketComment::query()
            ->where('ticket_id', $ticket->id)
            ->with('user')
            ->chronological()
            ->get();

        return response()->json([
            'comments' => TicketCommentResource::collection($comments),
        ]);
    }
}
