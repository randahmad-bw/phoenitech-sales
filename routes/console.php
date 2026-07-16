<?php

use App\Console\Commands\ExpireContracts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-complete active contracts whose end_date has passed — runs every day at midnight.
Schedule::command(ExpireContracts::class)->dailyAt('00:00');
