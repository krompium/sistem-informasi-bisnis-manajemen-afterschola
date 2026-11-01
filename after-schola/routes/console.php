<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SYS-04: reminder pembayaran harian (interval antar reminder diatur di pengaturan pembayaran).
Schedule::command('invoices:send-reminders')->dailyAt('08:00')->timezone(config('app.display_timezone'));
