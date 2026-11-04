<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ExportArtifact;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a generated export artifact to the user it belongs to.
 *
 * Replaces handing out `/storage/exports/...` URLs, which the web server
 * resolved straight off the public disk with no authentication at all.
 */
final class ExportDownloadController extends Controller
{
    public function __invoke(int $userId, string $filename): StreamedResponse
    {
        abort_unless($userId === (int) auth()->id(), 403);
        abort_unless(ExportArtifact::isSafeFilename($filename), 404);

        $disk = Storage::disk(ExportArtifact::DISK);
        $relative = 'exports/'.$userId.'/'.$filename;

        abort_unless($disk->exists($relative), 404);

        return $disk->download($relative);
    }
}
