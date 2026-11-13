<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Creates a snapshot from cron so backups do not depend on someone opening
 * the dashboard.
 */
class RunBackup extends Command
{
    protected $signature = 'cms:backup {kind=full : database, media or full} {--note= : Optional note}';

    protected $description = 'Create a site backup (database, media or full)';

    public function handle(BackupService $backups): int
    {
        $kind = (string) $this->argument('kind');

        if (! in_array($kind, Backup::KINDS, true)) {
            $this->error('Unknown backup kind. Use database, media or full.');

            return self::FAILURE;
        }

        $user = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'administrator'))
            ->first() ?? User::query()->first();

        if (! $user) {
            $this->error('No user exists to attribute the backup to. Run the seeder first.');

            return self::FAILURE;
        }

        $backup = $backups->create($user, $kind, $this->option('note') ?: 'Scheduled backup');

        $this->info("Created {$backup->filename} (".number_format($backup->size).' bytes).');

        return self::SUCCESS;
    }
}
