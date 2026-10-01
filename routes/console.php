<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Promemoria WhatsApp: richiede sul server il cron "php artisan schedule:run" ogni minuto.
Schedule::command('appointments:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
