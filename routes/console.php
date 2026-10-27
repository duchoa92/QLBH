<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('work-shifts:prune')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('system:send-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('system:send-debt-reminders')->weeklyOn(1, '08:00')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
