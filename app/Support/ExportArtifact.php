<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Naming for generated export artifacts (CSV/XLSX/PDF reports).
 *
 * These files hold whole-report data and used to be written to the public disk
 * with a name built from the clock alone (`tickets_report_Ymd_His.csv`). The
 * Docker image runs `storage:link`, so nginx served `/storage/exports/...`
 * without any authentication: anyone able to guess a minute could download
 * somebody else's report, and two exports triggered in the same second
 * overwrote each other. Names are now random and the artifacts live on the
 * private disk, reachable only through the authorized download route.
 */
final class ExportArtifact
{
    /**
     * Accepted filename shape, used both by the route constraint and the controller.
     */
    public const FILENAME_PATTERN = '[A-Za-z0-9_-]+\.(csv|xlsx|pdf)';

    public const DISK = 'local';

    /**
     * Builds the disk-relative path for a new artifact of the given kind.
     */
    public static function path(int $userId, string $slug, string $extension): string
    {
        $name = sprintf(
            '%s-%s-%s.%s',
            self::slug($slug),
            now()->format('Ymd_His'),
            Str::random(24),
            self::extension($extension),
        );

        return 'exports/'.$userId.'/'.$name;
    }

    /**
     * Link that serves the artifact through the authorized download route.
     */
    public static function link(string $relativePath): string
    {
        return '/exports/'.self::userId($relativePath).'/'.self::filename($relativePath);
    }

    public static function filename(string $relativePath): string
    {
        return basename($relativePath);
    }

    /**
     * Owner segment of the path, which the download route compares with the caller.
     */
    public static function userId(string $relativePath): int
    {
        return (int) explode('/', trim($relativePath, '/'))[1];
    }

    public static function isSafeFilename(string $filename): bool
    {
        return preg_match('/\A'.self::FILENAME_PATTERN.'\z/', $filename) === 1;
    }

    private static function slug(string $slug): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($slug)) ?? '';

        return $clean === '' ? 'export' : $clean;
    }

    private static function extension(string $extension): string
    {
        $clean = strtolower(ltrim($extension, '.'));

        return in_array($clean, ['csv', 'xlsx', 'pdf'], true) ? $clean : 'csv';
    }
}
