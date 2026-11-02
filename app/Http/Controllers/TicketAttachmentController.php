<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadPhotoRequest;
use App\Http\Resources\TicketAttachmentResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class TicketAttachmentController extends Controller
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Uploads and associates a new photo to a ticket.
     */
    public function store(UploadPhotoRequest $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('attachPhoto', $ticket);

        $file = $request->file('photo');
        $realMime = $file->getMimeType();

        // 2. Additional security validation of the real MIME type
        if (! in_array($realMime, self::ALLOWED_MIMES, true)) {
            return response()->json([
                'message' => __('ticket_media.Tipo de ficheiro não permitido.'),
            ], 422);
        }

        // 3. Safe filename processing and storage write
        //    The extension is derived from the real MIME (never from the client-submitted name)
        $extension = self::EXTENSION_BY_MIME[$realMime];
        $safeFilename = Str::uuid().'.'.$extension;
        $path = $file->storeAs('ticket_photos', $safeFilename, 'public');

        // 4. Register the attachment in the database
        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->authenticatedUser($request)->id,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $safeFilename,
            'path' => $path,
            'disk' => 'public',
            'extension' => $extension,
            'mime_type' => $realMime,
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'message' => __('messages.Fotografia carregada com sucesso.'),
            'attachment' => new TicketAttachmentResource($attachment),
        ], 201);
    }

    /**
     * Lists all attachments associated with a ticket.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('view', $ticket);

        $ticket->loadMissing('attachments');

        return response()->json([
            'attachments' => TicketAttachmentResource::collection($ticket->attachments),
        ]);
    }

    /**
     * Removes a specific attachment from a ticket.
     */
    public function destroy(Request $request, Ticket $ticket, TicketAttachment $attachment): JsonResponse
    {
        // 1. Authorization via Policy for photo deletion
        $this->authorize('deletePhoto', $ticket);

        // 2. Ensure referential integrity between the attachment and the ticket
        if ($attachment->ticket_id !== $ticket->id) {
            return response()->json([
                'message' => __('tickets.Anexo não encontrado para este ticket.'),
            ], 404);
        }

        // 3. Remove the physical file from storage if it exists
        if (Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }

        // 4. Delete the database record
        $attachment->delete();

        return response()->json([
            'message' => __('messages.Fotografia removida com sucesso.'),
        ]);
    }
}
