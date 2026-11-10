<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeeDocumentController extends Controller
{
    public function index(Request $request, Employee $employee): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.view');
        $employee->load(['documents' => fn ($q) => $q->latest()]);

        return view('ui.hr.documents.index', compact('employee'));
    }

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.manage');

        $file = $request->file('file');
        if (! $file) {
            return redirect()->back()->with('error', 'Ficheiro inválido.');
        }

        $path = $file->store('employee-documents/'.$employee->id, 'local');
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()?->id,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('ui.hr.employees.documents.index', $employee)->with('success', 'Documento carregado com sucesso.');
    }

    public function download(Request $request, Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.view');
        abort_unless($document->employee_id === $employee->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function destroy(Request $request, Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.manage');
        abort_unless($document->employee_id === $employee->id, 404);

        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }
        $document->delete();

        return redirect()->route('ui.hr.employees.documents.index', $employee)->with('success', 'Documento removido com sucesso.');
    }

    private function authorizeAdminOrPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->hasPermission($permission))) {
            abort(403);
        }
    }
}
