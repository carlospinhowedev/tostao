<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A consistent copy of the database, taken while the app keeps running.
 *
 * SQLite is the default and the one with a safe built-in answer: VACUUM INTO
 * writes a compact snapshot without locking readers out. For MySQL or
 * PostgreSQL, use the database's own dump tool (see docs/DEPLOY.md).
 */
class Backup
{
    /** @return string path of the new backup */
    public function run(int $keep = 14): string
    {
        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException(__('Automatic backups only cover SQLite. Use mysqldump or pg_dump for this database; see docs/DEPLOY.md.'));
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $name = 'backups/tostao-'.now()->format('Ymd-His').'.sqlite';
        $path = $disk->path($name);

        // The path goes into an SQL string, so quote it the way SQLite expects.
        DB::statement('VACUUM INTO '.DB::getPdo()->quote($path));

        $this->prune($keep);

        return $path;
    }

    /** Keep only the newest $keep backups. */
    public function prune(int $keep): int
    {
        $disk = Storage::disk('local');
        $files = collect($disk->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.sqlite'))
            ->sortDesc()->values();

        $old = $files->slice(max(1, $keep));
        $old->each(fn ($f) => $disk->delete($f));

        return $old->count();
    }
}
