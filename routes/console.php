<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 🔥 PERBAIKAN: Samakan string di dalam command() dengan $signature di file Robot 🔥
Schedule::command('bills:generate-recurring')->dailyAt('01:00');
