<?php

namespace App\Http\Controllers;

use App\Actions\CreatePublicTicketAction;
use App\Enums\NotificationTypeEnum;
use App\Enums\PublicTicketProblemTypeEnum;
use App\Http\Requests\PublicStoreTicketRequest;
use App\Jobs\GenerateAiRecommendationJob;
use App\Models\Equipment;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\NotificationCreatorService;
use App\Support\PhotoUploadPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PublicTicketController extends Controller
{
    public function __construct(
        private readonly CreatePublicTicketAction $createPublicTicket,
        private readonly NotificationCreatorService $notificationCreatorService,
    ) {}

    /**
     * Public damage report form, accessed via QR Code.
     */
    public function create(Request $request): View
    {
        $machineId = (int) $request->query('machine_id');

        // O QR é público e o `machine_id` é sequencial: qualquer pessoa pode percorrer
        // 1..N e ler o que aqui for renderizado. Por isso só a categoria é carregada —
        // o suficiente para o reportante reconhecer a máquina. `asset_tag`, marca,
        // modelo e sala ficam fora do ecrã (inventário interno, localização).
        $equipment = Equipment::query()
            ->with('category')
            ->findOrFail($machineId);

        return view('ui.tickets.public.create', [
            'equipment' => $equipment,
            'problemTypes' => PublicTicketProblemTypeEnum::cases(),
            // The picker must only offer what the validator accepts; otherwise the reporter
            // chooses a file and only learns after submitting that it was refused.
            'photoAccept' => implode(',', array_unique(array_map(
                static fn (string $type): string => 'image/'.($type === 'jpg' ? 'jpeg' : $type),
                PhotoUploadPolicy::allowedTypes()
            ))),
        ]);
    }

    /**
     * Registers the public ticket and notifies administrators.
     */
    public function store(PublicStoreTicketRequest $request): RedirectResponse
    {
        /** @var array{equipment_id: int|string, problem_type: string, description: string, reporter_name?: string|null, reporter_contact?: string|null} $validated */
        $validated = $request->validated();

        $equipment = Equipment::query()->findOrFail((int) $validated['equipment_id']);

        $problemType = PublicTicketProblemTypeEnum::normalize($validated['problem_type'])
            ?? PublicTicketProblemTypeEnum::Other;

        $ticket = $this->createPublicTicket->execute(
            equipment: $equipment,
            problemType: $problemType,
            description: $validated['description'],
            reporterName: $validated['reporter_name'] ?? null,
            reporterContact: $validated['reporter_contact'] ?? null,
        );

        if ($request->hasFile('photo')) {
            $this->storePhoto($ticket, $request);
        }

        // AI technician recommendation processed in the background after commit.
        GenerateAiRecommendationJob::dispatch($ticket)->afterCommit();

        $this->notifyAdmins($ticket);

        return redirect()->to(
            URL::temporarySignedRoute('ticket.public.success', now()->addDay(), ['ticket' => $ticket->id])
        );
    }

    /**
     * Confirmation screen with the created ticket number.
     */
    public function success(Ticket $ticket): View
    {
        return view('ui.tickets.public.success', [
            'ticket' => $ticket->load(['equipment', 'status']),
        ]);
    }

    /**
     * Saves the report photo as a ticket attachment (without user account).
     */
    private function storePhoto(Ticket $ticket, PublicStoreTicketRequest $request): void
    {
        $file = $request->file('photo');

        if ($file === null) {
            return;
        }

        // The extension comes from the detected MIME, never from the client-submitted name.
        $realMime = $file->getMimeType();
        $extension = PhotoUploadPolicy::extensionFor($realMime);
        $safeFilename = Str::uuid().'.'.$extension;
        $path = $file->storeAs('ticket_photos', $safeFilename, 'public');

        TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $safeFilename,
            'path' => $path,
            'disk' => 'public',
            'extension' => $extension,
            'mime_type' => $realMime,
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Notifies all administrators about the new ticket creation.
     */
    private function notifyAdmins(Ticket $ticket): void
    {
        $equipmentName = $ticket->equipment?->name;

        $this->notificationCreatorService->createForAdmins(
            title: __('tickets.Novo Ticket Reportado'),
            message: __('equipment.:reference — :equipment (:priority)', [
                'reference' => $ticket->reference,
                'equipment' => is_string($equipmentName) ? $equipmentName : (string) ($ticket->equipment_id ?? ''),
                'priority' => $ticket->priority,
            ]),
            type: NotificationTypeEnum::TicketCreated->value,
            link: route('ui.tickets.show', $ticket),
        );
    }
}
