<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Promemoria WhatsApp automatici: sul server serve il cron "php artisan schedule:run" ogni minuto.
// Senza le chiavi WAHA nel .env il comando non fa nulla.
Schedule::command('appointments:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
