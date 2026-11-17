<?php

use App\Services\RecurringService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

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
