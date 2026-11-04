<?php

declare(strict_types=1);

namespace Tests\Feature\Web\Controllers;

use App\Support\ExportArtifact;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class ExportDownloadControllerTest extends FeatureTestCase
{
    /**
     * Writes a report artifact for the given user on the private disk.
     */
    private function artifactFor(int $userId, string $contents = 'id,titulo'): string
    {
        $disk = Storage::disk(ExportArtifact::DISK);
        $relative = ExportArtifact::path($userId, 'tickets_report', 'csv');

        $disk->put($relative, $contents);

        return $relative;
    }

    #[Test]
    public function the_owner_downloads_their_generated_report(): void
    {
        Storage::fake('local');
        $user = $this->createUserWithPassword('user', 'dono@example.com', 'Password123!');
        $relative = $this->artifactFor($user->id, 'id,titulo');

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->get(ExportArtifact::link($relative));

        $response->assertOk();
        $response->assertDownload(ExportArtifact::filename($relative));
        // The payload is streamed, not buffered: run the callback to read what the
        // owner actually receives.
        ob_start();
        $response->sendContent();
        $this->assertSame('id,titulo', trim((string) ob_get_clean()));
    }

    #[Test]
    public function another_authenticated_user_cannot_read_the_report(): void
    {
        Storage::fake('local');
        $owner = $this->createUserWithPassword('user', 'dono@example.com', 'Password123!');
        $intruder = $this->createUserWithPassword('user', 'intruso@example.com', 'Password123!');
        $relative = $this->artifactFor($owner->id);

        $response = $this->withHeader('X-Auth-Token', $intruder->api_token)
            ->getJson(ExportArtifact::link($relative));

        $response->assertForbidden();
    }

    #[Test]
    public function an_admin_cannot_read_another_users_report(): void
    {
        Storage::fake('local');
        $owner = $this->createUserWithPassword('user', 'dono@example.com', 'Password123!');
        $admin = $this->createUserWithPassword('admin', 'admin@example.com', 'Password123!');
        $relative = $this->artifactFor($owner->id);

        $this->withHeader('X-Auth-Token', $admin->api_token)
            ->getJson(ExportArtifact::link($relative))
            ->assertForbidden();
    }

    #[Test]
    public function a_guest_cannot_download_reports(): void
    {
        Storage::fake('local');
        $user = $this->createUserWithPassword('user', 'dono@example.com', 'Password123!');
        $relative = $this->artifactFor($user->id);

        $this->getJson(ExportArtifact::link($relative))->assertUnauthorized();
        $this->get(ExportArtifact::link($relative))->assertRedirect('/ui/login');
    }

    #[Test]
    public function a_filename_that_does_not_match_the_pattern_is_rejected(): void
    {
        Storage::fake('local');
        $user = $this->createUserWithPassword('user', 'dono@example.com', 'Password123!');
        $this->artifactFor($user->id);

        foreach (['../tickets_report.csv', 'tickets_report.csv.php', 'report.sh'] as $filename) {
            $this->withHeader('X-Auth-Token', $user->api_token)
                ->getJson('/exports/'.$user->id.'/'.rawurlencode($filename))
                ->assertNotFound();
        }
    }

    #[Test]
    public function a_missing_artifact_returns_404_and_does_not_leak_other_users_files(): void
    {
        Storage::fake('local');
        $owner = $this->createUserWithPassword('user', 'dono@example.com', 'Password123!');
        $other = $this->createUserWithPassword('user', 'outro@example.com', 'Password123!');
        $relative = $this->artifactFor($owner->id);

        // Mesmo nome, dono diferente: o ficheiro existe, mas não pertence a quem pede.
        $this->withHeader('X-Auth-Token', $other->api_token)
            ->getJson(ExportArtifact::link($relative))
            ->assertForbidden();

        $this->withHeader('X-Auth-Token', $owner->api_token)
            ->getJson('/exports/'.$owner->id.'/nao-existe-123456789012345678901234.csv')
            ->assertNotFound();
    }

    #[Test]
    public function artifacts_are_written_outside_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = $this->createUserWithPassword('user', 'dono@example.com', 'Password123!');
        $relative = $this->artifactFor($user->id);

        $this->assertTrue(Storage::disk('local')->exists($relative));
        $this->assertFalse(Storage::disk('public')->exists($relative));
    }
}
