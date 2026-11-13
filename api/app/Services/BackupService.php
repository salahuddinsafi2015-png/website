<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Creates and restores site snapshots.
 *
 * Database snapshots are newline-delimited JSON (one object per line) rather
 * than SQL dumps, so they restore identically on every supported driver
 * (SQLite, MySQL, PostgreSQL) without binary dependencies such as mysqldump.
 *
 * Restores bring data back; the schema itself stays governed by migrations.
 */
class BackupService
{
    /**
     * Tables holding disposable runtime state. Backing them up would couple a
     * restore to stale queues/caches, so they are skipped.
     *
     * @var array<int, string>
     */
    private const TRANSIENT_TABLES = [
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        // Backup rows reference files that may since have been pruned.
        'backups',
    ];

    private const FINGERPRINT = ['format' => 'lumen-backup', 'version' => 1];

    public function diskName(): string
    {
        return (string) config('cms.backups.disk', 'local');
    }

    public function create(User $user, string $kind, ?string $note = null): Backup
    {
        if (! in_array($kind, Backup::KINDS, true)) {
            throw new InvalidArgumentException("Unknown backup kind [{$kind}].");
        }

        $disk = Storage::disk($this->diskName());
        $directory = 'backups';
        $disk->makeDirectory($directory);

        $extension = $kind === Backup::KIND_DATABASE ? 'ndjson' : 'zip';
        // A unique suffix matters: a safety snapshot taken moments after the
        // original would otherwise reuse the same second-precision name and
        // overwrite the very backup it is about to restore.
        $filename = 'backup-'.now()->format('Ymd-His').'-'.$kind.'-'.Str::lower(Str::random(8)).'.'.$extension;
        $path = $directory.'/'.$filename;
        $absolute = $disk->path($path);

        if ($kind === Backup::KIND_DATABASE) {
            $this->writeDatabaseDump($absolute);
        } elseif ($kind === Backup::KIND_MEDIA) {
            $this->writeMediaArchive($absolute);
        } else {
            $this->writeFullArchive($absolute);
        }

        if (! is_file($absolute)) {
            throw new RuntimeException('The backup file could not be written.');
        }

        $backup = Backup::create([
            'user_id' => $user->getKey(),
            'kind' => $kind,
            'disk' => $this->diskName(),
            'path' => $path,
            'filename' => $filename,
            'size' => (int) filesize($absolute),
            'checksum' => (string) hash_file('sha256', $absolute),
            'note' => $note,
        ]);

        $this->pruneExcess();

        return $backup;
    }

    /**
     * Restore a snapshot. The current state is snapshotted first, so a restore
     * can itself be undone from the backups list.
     *
     * @return array{restored: Backup, safety: Backup}
     */
    public function restore(Backup $backup, User $user): array
    {
        $absolute = Storage::disk($backup->disk)->path($backup->path);

        if (! is_file($absolute)) {
            throw new RuntimeException('The backup file is missing from storage.');
        }

        $checksum = hash_file('sha256', $absolute);

        if (! hash_equals((string) $backup->checksum, (string) $checksum)) {
            throw new RuntimeException('The backup failed its integrity check; refusing to restore it.');
        }

        file_put_contents('/tmp/dbg.log', 'restore file='.$absolute.' size='.filesize($absolute)."\n", FILE_APPEND);
        $safety = $this->create(
            $user,
            $backup->kind,
            'Automatic snapshot taken before restoring backup #'.$backup->getKey(),
        );

        if ($backup->kind === Backup::KIND_DATABASE) {
            $this->restoreDatabaseFrom($absolute);
        } else {
            $this->restoreArchive($absolute, $backup->kind);
        }

        return ['restored' => $backup->refresh(), 'safety' => $safety];
    }

    public function delete(Backup $backup): void
    {
        $disk = Storage::disk($backup->disk);

        if ($disk->exists($backup->path)) {
            $disk->delete($backup->path);
        }

        $backup->delete();
    }

    public function download(Backup $backup): StreamedResponse
    {
        return Storage::disk($backup->disk)->download($backup->path, $backup->filename);
    }

    /**
     * @return array<int, string>
     */
    public function dumpableTables(): array
    {
        $tables = array_map(
            fn (array|string $table) => is_array($table) ? $table['name'] : $table,
            Schema::getTables(),
        );

        return array_values(array_filter(
            array_unique($tables),
            fn (string $table) => ! str_starts_with($table, 'sqlite_')
                && ! in_array($table, self::TRANSIENT_TABLES, true),
        ));
    }

    /**
     * Tables ordered parents-first, so inserts respect foreign keys even when
     * they cannot be disabled (for example inside a wrapping transaction).
     *
     * @return array<int, string>
     */
    public function orderedTables(): array
    {
        $tables = $this->dumpableTables();
        $known = array_flip($tables);
        $visited = [];
        $order = [];

        $visit = function (string $table) use (&$visit, &$visited, &$order, $known): void {
            if (isset($visited[$table])) {
                return;
            }

            $visited[$table] = true;

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_table'] ?? null;

                if (is_string($parent) && $parent !== $table && isset($known[$parent])) {
                    $visit($parent);
                }
            }

            $order[] = $table;
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        return $order;
    }

    private function writeDatabaseDump(string $absolute): void
    {
        $handle = $this->openWriter($absolute);
        $tables = $this->orderedTables();

        $this->writeLine($handle, [
            ...self::FINGERPRINT,
            'kind' => Backup::KIND_DATABASE,
            'driver' => DB::connection()->getDriverName(),
            'created_at' => now()->toIso8601String(),
            'tables' => count($tables),
        ]);

        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            $this->writeLine($handle, ['table' => $table, 'columns' => $columns]);

            DB::table($table)
                ->orderBy($columns[0])
                ->chunk(500, function ($rows) use ($handle, $table): void {
                    foreach ($rows as $row) {
                        $this->writeLine($handle, [
                            'table' => $table,
                            'row' => (array) $row,
                        ]);
                    }
                });
        }

        fclose($handle);
    }

    private function writeMediaArchive(string $absolute): void
    {
        $zip = new ZipArchive();

        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The media archive could not be created.');
        }

        $disk = Storage::disk($this->mediaDiskName());
        $files = $disk->allFiles();
        sort($files);

        foreach ($files as $file) {
            $zip->addFile($disk->path($file), $file);
        }

        $this->writeLineToZip($zip, 'manifest.json', [
            ...self::FINGERPRINT,
            'kind' => Backup::KIND_MEDIA,
            'created_at' => now()->toIso8601String(),
            'files' => count($files),
        ]);

        $zip->close();
    }

    private function writeFullArchive(string $absolute): void
    {
        $zip = new ZipArchive();

        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The backup archive could not be created.');
        }

        $temporary = Storage::disk($this->diskName())->path('backups/database-tmp-'.uniqid().'.ndjson');
        $this->writeDatabaseDump($temporary);
        $zip->addFile($temporary, 'database.ndjson');

        $disk = Storage::disk($this->mediaDiskName());
        $files = $disk->allFiles();
        sort($files);

        foreach ($files as $file) {
            $zip->addFile($disk->path($file), $file);
        }

        $this->writeLineToZip($zip, 'manifest.json', [
            ...self::FINGERPRINT,
            'kind' => Backup::KIND_FULL,
            'created_at' => now()->toIso8601String(),
            'database' => 'database.ndjson',
            'files' => count($files),
            'tables' => count($this->dumpableTables()),
        ]);

        $zip->close();
        @unlink($temporary);
    }

    private function restoreDatabaseFrom(string $absolute): void
    {
        $handle = fopen($absolute, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The database snapshot could not be read.');
        }

        Schema::disableForeignKeyConstraints();

        try {
            // First pass: collect the table list from the headers.
            $tables = [];

            while (($line = fgets($handle)) !== false) {
                $data = json_decode(trim($line), true);

                if (is_array($data) && isset($data['table'], $data['columns']) && ! isset($data['row'])) {
                    $tables[] = (string) $data['table'];
                }
            }

            rewind($handle);

            DB::transaction(function () use ($handle, $tables): void {
                // Clear children before parents so foreign keys stay satisfied
                // even when the driver ignores the disable pragma.
                foreach (array_reverse($tables) as $table) {
                    DB::table($table)->delete();
                }

                // Second pass: rows arrive in dependency order, parents first.
                $pending = [];
                $flush = function (string $table) use (&$pending): void {
                    foreach (array_chunk($pending[$table] ?? [], 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }

                    unset($pending[$table]);
                };

                while (($line = fgets($handle)) !== false) {
                    $data = json_decode(trim($line), true);

                    if (! is_array($data) || isset($data['format']) || ! isset($data['table'], $data['row'])) {
                        continue;
                    }

                    $pending[$data['table']][] = $data['row'];

                    if (count($pending[$data['table']]) >= 200) {
                        $flush((string) $data['table']);
                    }
                }

                foreach (array_keys($pending) as $table) {
                    $flush((string) $table);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
            fclose($handle);
        }
    }

    private function restoreArchive(string $absolute, string $kind): void
    {
        $zip = new ZipArchive();

        if ($zip->open($absolute) !== true) {
            throw new RuntimeException('The backup archive could not be opened.');
        }

        try {
            $databaseEntry = $kind === Backup::KIND_FULL ? 'database.ndjson' : null;

            if ($databaseEntry !== null) {
                $stream = $zip->getStream($databaseEntry);

                if ($stream === false) {
                    throw new RuntimeException('The archive does not contain a database snapshot.');
                }

                $temporary = Storage::disk($this->diskName())
                    ->path('backups/restore-tmp-'.uniqid().'.ndjson');
                $writer = fopen($temporary, 'wb');
                stream_copy_to_stream($stream, $writer);
                fclose($writer);
                fclose($stream);

                $this->restoreDatabaseFrom($temporary);
                @unlink($temporary);
            }

            $media = Storage::disk($this->mediaDiskName());

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->getNameIndex($index);

                if ($entry === false || str_ends_with($entry, '/')
                    || $entry === 'manifest.json' || $entry === $databaseEntry) {
                    continue;
                }

                $stream = $zip->getStream($entry);

                if ($stream === false) {
                    continue;
                }

                $media->writeStream($entry, $stream);
                fclose($stream);
            }
        } finally {
            $zip->close();
        }
    }

    private function pruneExcess(): void
    {
        $keep = (int) config('cms.backups.keep', 20);

        if ($keep <= 0) {
            return;
        }

        Backup::query()
            ->orderByDesc('id')
            ->skip($keep)
            ->take(200)
            ->get()
            ->each(fn (Backup $old) => $this->delete($old));
    }

    private function mediaDiskName(): string
    {
        return (string) config('cms.media.disk', 'public');
    }

    /**
     * @return resource
     */
    private function openWriter(string $absolute)
    {
        $handle = fopen($absolute, 'wb');

        if ($handle === false) {
            throw new RuntimeException('The snapshot file could not be opened for writing.');
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $data
     */
    private function writeLine($handle, array $data): void
    {
        fwrite($handle, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeLineToZip(ZipArchive $zip, string $name, array $data): void
    {
        $zip->addFromString(
            $name,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }
}
