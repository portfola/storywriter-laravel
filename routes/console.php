<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule ElevenLabs cost monitoring
Schedule::command('elevenlabs:monitor-cost --period=today --notify')
    ->dailyAt('18:00') // Run at 6 PM daily
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
