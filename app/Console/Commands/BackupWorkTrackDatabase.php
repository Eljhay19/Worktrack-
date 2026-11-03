<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Support\MySqlClientOptionsFile;
use Symfony\Component\Process\Process;
use Throwable;

class BackupWorkTrackDatabase extends Command
{
    protected $signature = 'worktrack:backup
        {--keep= : Number of encrypted database snapshots to keep}';

    protected $description = 'Create an encrypted private backup of the WORKTRACK MySQL database';

    public function handle(): int
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}", []);

        if (!in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->components->error('WORKTRACK database backups currently support MySQL and MariaDB only.');

            return self::FAILURE;
        }

        if (blank(config('app.key'))) {
            $this->components->error('APP_KEY must be set before encrypted backups can be created.');

            return self::FAILURE;
        }

        $database = (string) ($connection['database'] ?? '');
        if ($database === '') {
            $this->components->error('The active database connection has no database name configured.');

            return self::FAILURE;
        }

        $binary = (string) config('worktrack.mysql_dump_binary', 'mysqldump');
        $defaultsFile = null;

        try {
            $defaultsFile = MySqlClientOptionsFile::create($connection);
            $process = new Process([
                $binary,
                '--defaults-extra-file='.$defaultsFile,
                '--single-transaction',
                '--skip-lock-tables',
                '--hex-blob',
                '--routines',
                '--triggers',
                '--events',
                '--no-tablespaces',
                '--add-drop-database',
                '--databases',
                $database,
            ], base_path());
            $process->setTimeout((int) config('worktrack.backup_timeout', 300));
            $process->run();

            if (!$process->isSuccessful() || trim($process->getOutput()) === '') {
                Log::error('WORKTRACK database backup failed.', [
                    'connection' => $connectionName,
                    'exit_code' => $process->getExitCode(),
                ]);
                $this->components->error('Database backup failed. Check the database connection and mysqldump configuration.');

                return self::FAILURE;
            }

            $encryptedDump = Crypt::encryptString($process->getOutput());
            $filename = 'worktrack-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql.enc';
            $disk = Storage::disk('worktrack-backups');
            $disk->put($filename, $encryptedDump);
            MySqlClientOptionsFile::restrictFilePermissions($disk->path($filename));
            $deleted = $this->pruneOldBackups($disk, (int) ($this->option('keep') ?: config('worktrack.backup_retention', 14)));

            $this->components->info("Encrypted backup saved: storage/app/private/worktrack-backups/{$filename}");
            if ($deleted > 0) {
                $this->line("Removed {$deleted} expired backup(s).");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('WORKTRACK database backup could not be completed.', [
                'connection' => $connectionName,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->components->error('Database backup could not be completed. Check the private backup disk, APP_KEY, and mysqldump setup.');

            return self::FAILURE;
        } finally {
            MySqlClientOptionsFile::remove($defaultsFile);
        }
    }

    private function pruneOldBackups($disk, int $keep): int
    {
        $keep = max(1, min($keep, 365));
        $files = array_values(array_filter(
            $disk->files(),
            fn (string $file): bool => preg_match('/^worktrack-\d{8}-\d{6}-[a-f0-9]{8}\.sql\.enc$/', basename($file)) === 1,
        ));
        rsort($files, SORT_STRING);

        $expired = array_slice($files, $keep);
        if ($expired !== []) {
            $disk->delete($expired);
        }

        return count($expired);
    }
}
