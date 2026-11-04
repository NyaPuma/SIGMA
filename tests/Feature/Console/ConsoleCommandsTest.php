<?php

namespace Tests\Feature\Console;

use App\Enums\UserRoleEnum;
use App\Models\Equipment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\TicketStatusService;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\DatabaseTestCase;
use Tests\Concerns\CreatesTickets;

class ConsoleCommandsTest extends DatabaseTestCase
{
    use CreatesTickets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTicketLookupData();
        app(TicketStatusService::class)->flush();
    }

    #[Test]
    public function db_backup_fails_gracefully_for_unknown_connection(): void
    {
        $exitCode = Artisan::call('db:backup', ['--connection' => 'nonexistent']);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('was not found', Artisan::output());
    }

    #[Test]
    public function db_backup_uses_pg_dump_for_postgres_connections(): void
    {
        $backupDir = storage_path('app/testing-backups');
        $pgDumpCommand = null;

        config([
            'database.connections.pg_backup' => [
                'driver' => 'pgsql',
                'host' => 'db.internal',
                'port' => 5433,
                'database' => 'sigma',
                'username' => 'sigma_app',
                'password' => 'super-secret',
            ],
            'backup.storage.enabled' => false,
            'backup.offsite.enabled' => false,
        ]);

        // The fake stands in for pg_dump: it records the command and writes the dump file
        // the command expects to find afterwards.
        Process::fake(function (PendingProcess $process) use (&$pgDumpCommand): FakeProcessResult {
            $command = is_string($process->command) ? $process->command : '';

            if (! str_starts_with($command, 'pg_dump')) {
                return Process::result();
            }

            $pgDumpCommand = $command;

            // escapeshellarg() wraps the path in double quotes on Windows but single quotes on
            // Linux, so both forms must be unwrapped — otherwise the captured target keeps its
            // quote characters and the write lands on a path that does not exist.
            if (preg_match('/--file=(?:"([^"]+)"|\'([^\']+)\'|(\S+))/', $command, $matches) === 1) {
                $target = '';

                foreach ([1, 2, 3] as $group) {
                    if (($matches[$group] ?? '') !== '') {
                        $target = $matches[$group];

                        break;
                    }
                }

                if ($target !== '') {
                    file_put_contents($target, "-- pg_dump export\n");
                }
            }

            return Process::result();
        });

        $exitCode = Artisan::call('db:backup', [
            '--connection' => 'pg_backup',
            '--path' => $backupDir,
            '--no-compress' => true,
        ]);

        $this->assertEquals(0, $exitCode, (string) Artisan::output());
        $this->assertStringNotContainsString('Unsupported driver', Artisan::output());
        $this->assertIsString($pgDumpCommand);

        // The secret must reach pg_dump through PGPASSWORD, never on the command line.
        $this->assertStringNotContainsString('super-secret', $pgDumpCommand);
        $this->assertStringContainsString('--dbname='.escapeshellarg('sigma'), $pgDumpCommand);
        $this->assertStringContainsString('--exclude-table='.escapeshellarg('failed_jobs'), $pgDumpCommand);

        $artifacts = glob($backupDir.DIRECTORY_SEPARATOR.'backup_*.sql') ?: [];
        foreach ($artifacts as $artifact) {
            unlink($artifact);
        }
        @rmdir($backupDir);
    }

    #[Test]
    public function fix_ticket_encoding_requires_mysql_driver(): void
    {
        $exitCode = Artisan::call('tickets:fix-encoding');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('MySQL', Artisan::output());
    }

    #[Test]
    public function audit_partition_requires_mysql_driver(): void
    {
        $exitCode = Artisan::call('audit:partition');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('MySQL/MariaDB', Artisan::output());
    }

    #[Test]
    public function telemetry_simulation_fails_without_admin_user(): void
    {
        Equipment::factory()->create(['active' => true]);

        $exitCode = Artisan::call('telemetry:simulate');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('administrator', mb_strtolower(Artisan::output()));
    }

    #[Test]
    public function telemetry_simulation_creates_tickets_when_anomaly_is_detected(): void
    {
        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);
        User::factory()->create(['profile_id' => $profile->id, 'active' => true]);
        Equipment::factory()->create(['active' => true]);

        $exitCode = Artisan::call('telemetry:simulate', [
            '--equipments' => 1,
            '--probability' => 100,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Simulation completed', Artisan::output());
        $this->assertEquals(1, Ticket::where('title', 'like', '[TELEMETRIA]%')->count());
    }

    #[Test]
    public function telemetry_simulation_skips_equipment_with_open_ticket(): void
    {
        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);
        $admin = User::factory()->create(['profile_id' => $profile->id, 'active' => true]);

        $equipment = Equipment::factory()->create(['active' => true]);
        $this->createTicket(['equipment_id' => $equipment->id]);

        $exitCode = Artisan::call('telemetry:simulate', [
            '--equipments' => 1,
            '--probability' => 100,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('already has an active ticket', Artisan::output());
        $this->assertEquals(0, Ticket::where('title', 'like', '[TELEMETRIA]%')->count());
    }

    #[Test]
    public function telemetry_simulation_supports_dry_run_without_persisting(): void
    {
        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);
        User::factory()->create(['profile_id' => $profile->id, 'active' => true]);
        Equipment::factory()->create(['active' => true]);

        $exitCode = Artisan::call('telemetry:simulate', [
            '--equipments' => 1,
            '--probability' => 100,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('[DRY-RUN]', Artisan::output());
        $this->assertEquals(0, Ticket::count());
    }
}
