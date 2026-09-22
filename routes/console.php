<?php

use App\Console\Commands\CloseAttendanceDay;
use App\Console\Commands\ExpireContracts;
use App\Console\Commands\PruneAuditLogs;
use App\Console\Commands\RemindTaskOwners;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-complete active contracts whose end_date has passed — runs every day at midnight.
Schedule::command(ExpireContracts::class)->dailyAt('00:00');

// Keep the audit trail within its retention window (config/audit.php).
Schedule::command(PruneAuditLogs::class)->weeklyOn(1, '01:30');

// Close yesterday's attendance: mark forgotten check-outs `incomplete` and
// record absences for scheduled days nobody showed up for. Runs at 02:00 in
// the company timezone, comfortably after any late shift has ended.
Schedule::command(CloseAttendanceDay::class)
    ->dailyAt('02:00')
    ->timezone(config('attendance.timezone'));

// What is due today and what is already late, told to the people holding it.
// Early enough to be read before the day is planned, and idempotent — the
// service refuses a second notice for the same task on the same day.
Schedule::command(RemindTaskOwners::class)
    ->dailyAt('07:30')
    ->timezone(config('attendance.timezone'));
