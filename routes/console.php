<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Advances trials, renewals, grace expiries, pending orders and licenses.
// Deterministic renewal keys make repeated runs safe.
Schedule::command('billing:sync')
    ->hourly()
    ->withoutOverlapping();
