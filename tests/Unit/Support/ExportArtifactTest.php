<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ExportArtifact;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ExportArtifactTest extends TestCase
{
    #[Test]
    public function two_artifacts_of_the_same_kind_never_share_a_name(): void
    {
        $first = ExportArtifact::path(7, 'tickets_report', 'csv');
        $second = ExportArtifact::path(7, 'tickets_report', 'csv');

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function the_path_is_scoped_to_the_owner_and_matches_the_route_constraint(): void
    {
        $relative = ExportArtifact::path(42, 'qrcodes-equipment', 'pdf');

        $this->assertStringStartsWith('exports/42/', $relative);
        $this->assertSame(42, ExportArtifact::userId($relative));
        $this->assertTrue(
            ExportArtifact::isSafeFilename(ExportArtifact::filename($relative)),
            'Generated names must satisfy the download route constraint.',
        );
    }

    #[Test]
    public function the_link_is_an_absolute_app_route_and_never_a_public_storage_url(): void
    {
        $relative = ExportArtifact::path(9, 'tickets_report', 'xlsx');

        $link = ExportArtifact::link($relative);

        $this->assertSame('/exports/9/'.ExportArtifact::filename($relative), $link);
        $this->assertStringNotContainsString('/storage/', $link);
    }

    #[Test]
    public function the_slug_is_sanitized_and_the_extension_is_whitelisted(): void
    {
        $relative = ExportArtifact::path(3, '../../etc/passwd', 'php');

        $filename = ExportArtifact::filename($relative);

        $this->assertStringNotContainsString('/', $filename);
        $this->assertStringEndsWith('.csv', $filename);
        $this->assertTrue(ExportArtifact::isSafeFilename($filename));
    }

    #[Test]
    #[DataProvider('unsafeFilenames')]
    public function unsafe_filenames_are_rejected(string $filename): void
    {
        $this->assertFalse(ExportArtifact::isSafeFilename($filename));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeFilenames(): array
    {
        return [
            'directory traversal' => ['../../tickets_report.csv'],
            'encoded traversal' => ['..%2F..%2Ftickets_report.csv'],
            'double extension' => ['tickets_report.csv.php'],
            'unexpected extension' => ['tickets_report.sh'],
            'empty name' => [''],
            'missing extension' => ['tickets_report'],
            'leading dotfile' => ['.env.csv'],
            'space in name' => ['tickets report.csv'],
        ];
    }
}
