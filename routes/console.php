<?php

use App\Services\Backup;
use App\Services\BankSync;
use App\Services\BrokerSync;
use App\Services\RecurringService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('tostao:sync-banks', function (BankSync $sync) {
    $count = $sync->syncAll();
    $this->info("Imported {$count} new transaction(s) from your banks.");
})->purpose('Fetch new transactions from connected banks');

Artisan::command('tostao:backup {--keep=14 : How many backups to keep}', function (Backup $backup) {
    $path = $backup->run(max(1, (int) $this->option('keep')));
    $this->info("Backup written to {$path}");
})->purpose('Save a copy of the database (SQLite) and prune the old ones');

Artisan::command('tostao:sync-brokers', function (BrokerSync $sync) {
    $count = $sync->syncAll();
    $this->info("Imported {$count} new transaction(s) from your brokers.");
})->purpose('Update the value and cash movements of linked investment accounts');

Artisan::command('tostao:recurring', function (RecurringService $service) {
    $count = $service->generateDue();
    $this->info("Created {$count} recurring transaction(s).");
})->purpose('Create the recurring transactions that fell due');

// Uploaded bank files are only needed until the import is confirmed.
Artisan::command('tostao:prune-imports', function () {
    $disk = Storage::disk('local');
    $removed = 0;

    foreach ($disk->files('imports') as $file) {
        if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
            $disk->delete($file);
            $removed++;
        }
    }

    $this->info("Removed {$removed} abandoned upload(s).");
})->purpose('Delete bank files uploaded more than a day ago and never imported');

Schedule::command('tostao:prune-imports')->daily();

// Optional: with a cron entry for `schedule:run` this runs every morning.
// Without it, the dashboard does the same work when someone opens it.
Schedule::command('tostao:recurring')->dailyAt('06:00');

// Only does anything when a bank is connected.
Schedule::command('tostao:sync-banks')->dailyAt('07:00');

// A copy every night; copy the backups folder somewhere else too (see docs/DEPLOY.md).
Schedule::command('tostao:backup')->dailyAt('03:00');
Schedule::command('tostao:sync-brokers')->dailyAt('07:30');
