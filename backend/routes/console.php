<?php

use App\Services\BackupService;
use App\Services\CreditService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Nada Crédit — tâches planifiées
|--------------------------------------------------------------------------
| Chaque nuit : marque les échéances dépassées et rafraîchit les statuts.
| En production : `* * * * * php artisan schedule:run` (cron).
*/
Schedule::call(function (CreditService $credits) {
    $credits->flagOverdueInstallments();

    \App\Models\Credit::active()->get()->each(fn ($credit) => $credits->refreshStatus($credit));
})->dailyAt('00:05')->name('credits:flag-overdue');

Schedule::call(function (BackupService $backups) {
    $backups->create();
    $backups->prune();
})->dailyAt('02:00')->name('db:backup');

Artisan::command('credits:refresh', function (CreditService $credits) {
    $count = $credits->flagOverdueInstallments();
    \App\Models\Credit::active()->get()->each(fn ($credit) => $credits->refreshStatus($credit));
    $this->info("Échéances marquées en retard : {$count}");
})->purpose('Met à jour les échéances en retard et les statuts de crédit');
