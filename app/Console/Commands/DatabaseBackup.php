<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

class DatabaseBackup extends Command
{
    protected $aliases = ['backup:run'];

    protected $signature = 'db:backup
                    {--connection= : The database connection to use}
                    {--path= : Custom path for the backups}
                    {--no-compress : Skip gzip compression}
                    {--offsite : Upload database and storage artifacts to the configured off-site disk}
                    {--clean : Remove backups older than the retention period}';

    protected $description = 'Creates a database backup using native tools (mysqldump/sqlite3)';

    public function handle(): int
    {
        $connection = $this->option('connection') ?? config('backup.database.connection') ?? config('database.default');
        $config = config("database.connections.{$connection}");

        if (! $config) {
            $this->error("The connection '{$connection}' was not found in config/database.php");

            return self::FAILURE;
        }

        $backupDir = $this->option('path') ?? config('backup.database.destination.path', storage_path('app/backups'));
        File::makeDirectory($backupDir, 0755, true, true);

        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup_{$timestamp}.sql";
        $filepath = rtrim($backupDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
        $artifacts = [];

        $this->info("Starting backup of connection: {$connection}");
        $this->info("Driver: {$config['driver']}");

        try {
            match ($config['driver']) {
                'mysql' => $this->backupMysql($config, $filepath),
                'sqlite' => $this->backupSqlite($config, $filepath),
                default => throw new RuntimeException("Unsupported driver: {$config['driver']}"),
            };

            $this->info("Backup created successfully: {$filepath}");
            $this->info('Original size: '.number_format(File::size($filepath)).' bytes');

            if (! $this->option('no-compress') && config('backup.database.compression', true)) {
                $filepath = $this->compressBackup($filepath);
            }

            $artifacts[] = $filepath;

            if ((bool) config('backup.storage.enabled', true)) {
                $storageArchive = $this->createStorageArchive($backupDir, $timestamp);
                $artifacts[] = $storageArchive;
                $this->info("Application storage archive created successfully: {$storageArchive}");
            }

            if ((bool) config('backup.offsite.enabled', false) || $this->option('offsite')) {
                foreach ($artifacts as $artifact) {
                    $this->uploadOffsite($artifact);
                }
            }

            if ($this->option('clean')) {
                $this->cleanOldBackups($backupDir);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to create the backup: {$e->getMessage()}");
            Log::error('Application backup failed', [
                'metric' => 'backup.failure',
                'connection' => $connection,
                'exception' => $e->getMessage(),
            ]);

            try {
                \Sentry\captureException($e);
            } catch (Throwable) {
                // Backup failure reporting must never hide the original failure.
            }

            // Remove the incomplete file if the process failed
            foreach (array_unique([...$artifacts, $filepath]) as $artifact) {
                if (File::exists($artifact)) {
                    File::delete($artifact);
                }
            }

            return self::FAILURE;
        }
    }

    private function backupMysql(array $config, string $filepath): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';

        $excludeTables = config('backup.database.exclude_tables', []);

        $ignoreArgs = $excludeTables
            ? implode(' ', array_map('escapeshellarg', array_map(fn ($t) => "--ignore-table={$database}.{$t}", $excludeTables)))
            : '';

        // We don't pass the password via CLI to avoid exposing it in the process table
        $cmd = sprintf(
            'mysqldump -h %s -P %d -u %s %s %s --routines --triggers --single-transaction --result-file=%s',
            escapeshellarg($host),
            (int) $port,
            escapeshellarg($username),
            escapeshellarg($database),
            $ignoreArgs,
            escapeshellarg($filepath)
        );

        // Inject the password securely via the MYSQL_PWD environment variable
        $result = Process::env(['MYSQL_PWD' => $password])
            ->timeout(600) // 10-minute timeout for larger databases
            ->run($cmd);

        if ($result->failed()) {
            throw new RuntimeException('mysqldump failed: '.$result->errorOutput());
        }
    }

    private function backupSqlite(array $config, string $filepath): void
    {
        $database = $config['database'] ?? database_path('database.sqlite');

        if (! File::exists($database)) {
            throw new RuntimeException("SQLite database file not found: {$database}");
        }

        $cmd = sprintf(
            'sqlite3 %s .dump > %s',
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        $result = Process::timeout(600)->run($cmd);

        if ($result->failed()) {
            throw new RuntimeException('sqlite3 dump failed: '.$result->errorOutput());
        }
    }

    /**
     * Compresses the backup file using PHP's native zlib extension.
     */
    private function compressBackup(string $filepath): string
    {
        $gzFile = $filepath.'.gz';

        $fpOut = gzopen($gzFile, 'wb9');
        $fpIn = fopen($filepath, 'rb');

        if (! $fpOut || ! $fpIn) {
            throw new RuntimeException('Could not initialise the Gzip compression streams.');
        }

        while (! feof($fpIn)) {
            $chunk = fread($fpIn, 524288); // Read in 512KB chunks

            if ($chunk === false || $chunk === '') {
                break;
            }

            gzwrite($fpOut, $chunk);
        }

        fclose($fpIn);
        gzclose($fpOut);

        if (File::exists($gzFile)) {
            File::delete($filepath);
            $this->info("Compressed successfully: {$gzFile} (".number_format(File::size($gzFile)).' bytes)');

            return $gzFile;
        }

        return $filepath;
    }

    private function cleanOldBackups(string $backupDir): void
    {
        $retentionDays = config('backup.retention.days', 30);
        $cutoff = now()->subDays($retentionDays);

        $files = File::glob($backupDir.DIRECTORY_SEPARATOR.'backup_*');
        $removed = 0;

        foreach ($files as $file) {
            if (File::lastModified($file) < $cutoff->timestamp) {
                File::delete($file);
                $removed++;
            }
        }

        $this->info("Removed {$removed} backups older than {$retentionDays} days.");
    }

    private function createStorageArchive(string $backupDir, string $timestamp): string
    {
        $archivePath = rtrim($backupDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."storage_{$timestamp}.zip";
        $root = (string) config('backup.storage.path', storage_path('app'));
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the application storage archive.');
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $backupPrefix = rtrim($backupDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || str_starts_with($file->getPathname(), $backupPrefix)) {
                continue;
            }

            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($rootPrefix)));
        }

        $zip->close();

        return $archivePath;
    }

    private function uploadOffsite(string $filepath): void
    {
        $disk = (string) config('backup.offsite.disk', 's3');
        $prefix = trim((string) config('backup.offsite.path', 'application-backups'), '/');
        $remotePath = $prefix.'/'.basename($filepath);
        $stream = fopen($filepath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Could not open backup artifact: {$filepath}");
        }

        try {
            $uploaded = Storage::disk($disk)->put($remotePath, $stream, ['visibility' => 'private']);
        } finally {
            fclose($stream);
        }

        if (! $uploaded) {
            throw new RuntimeException("Could not upload backup artifact to disk '{$disk}'.");
        }

        $this->info("Backup artifact uploaded to {$disk}: {$remotePath}");
        Log::info('Backup artifact uploaded successfully', [
            'metric' => 'backup.offsite_upload',
            'disk' => $disk,
            'path' => $remotePath,
        ]);
    }
}
