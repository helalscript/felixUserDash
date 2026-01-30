<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cancel abandoned orders (pending payment > 30 min) every 10 minutes
Schedule::command('orders:cancel-abandoned')->everyTenMinutes();
