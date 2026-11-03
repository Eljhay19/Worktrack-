<?php

namespace App\Console\Commands;

use App\Support\MySqlClientOptionsFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class RestoreWorkTrackDatabase extends Command
{
    protected $signature = 'worktrack:restore
        {backup : Encrypted backup filename from the private WorkTrack backup folder}
        {--force : Skip the database-name confirmation prompt}';

    protected $description = 'Restore a WorkTrack database from an encrypted private snapshot';

    public function handle(): int
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}", []);
        if (!in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->components->error('WORKTRACK database restore currently supports MySQL and MariaDB only.');

            return self::FAILURE;
        }

        $database = (string) ($connection['database'] ?? '');
        if ($database === '') {
            $this->components->error('The active database connection has no database name configured.');

            return self::FAILURE;
        }

        $backup = (string) $this->argument('backup');
        if (basename($backup) !== $backup || preg_match('/^worktrack-\d{8}-\d{6}-[a-f0-9]{8}\.sql\.enc$/', $backup) !== 1) {
            $this->components->error('Pass a valid encrypted WorkTrack backup filename, without a folder path.');

            return self::FAILURE;
        }

        $disk = Storage::disk('worktrack-backups');
        if (!$disk->exists($backup)) {
            $this->components->error('That backup file was not found in the private backup folder.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->warn("This replaces the full `{$database}` database with the selected backup.");
        if (!$this->option('force') && $this->ask("Type {$database} to continue") !== $database) {
            $this->components->info('Restore cancelled.');

            return self::SUCCESS;
        }

        $defaultsFile = null;
        try {
            $sql = Crypt::decryptString($disk->get($backup));
            if (trim($sql) === '') {
                throw new \RuntimeException('The decrypted backup is empty.');
            }
            $quotedDatabase = preg_quote(str_replace('`', '``', $database), '/');
            if (preg_match('/DROP DATABASE IF EXISTS\s+`'.$quotedDatabase.'`/i', $sql) !== 1) {
                $this->components->error('This snapshot does not contain a full database replacement dump and cannot be restored safely by this command.');

                return self::FAILURE;
            }

            $defaultsFile = MySqlClientOptionsFile::create($connection);
            $binary = (string) config('worktrack.mysql_binary', 'mysql');
            $process = new Process([
                $binary,
                '--defaults-extra-file='.$defaultsFile,
            ], base_path());
            $process->setInput($sql);
            $process->setTimeout((int) config('worktrack.backup_timeout', 300));
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('WORKTRACK database restore failed.', [
                    'connection' => $connectionName,
                    'backup' => $backup,
                    'exit_code' => $process->getExitCode(),
                ]);
                $this->components->error('Database restore failed. Check the MySQL connection and the selected backup.');

                return self::FAILURE;
            }

            $this->components->info("Database {$database} was restored from {$backup}.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('WORKTRACK database restore could not be completed.', [
                'connection' => $connectionName,
                'backup' => $backup,
                'exception' => $exception::class,
            ]);
            $this->components->error('Database restore could not be completed. Check the backup, APP_KEY, and MySQL setup.');

            return self::FAILURE;
        } finally {
            MySqlClientOptionsFile::remove($defaultsFile);
        }
    }
}
