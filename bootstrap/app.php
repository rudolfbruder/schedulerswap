<?php

use App\Jobs\HeartbeatJob;
use App\Scheduling\Contracts\Scheduler as PortableScheduler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        /** @var PortableScheduler $scheduler */
        $scheduler = app(PortableScheduler::class);

        $scheduler->job(new HeartbeatJob)
            ->everyMinute()
            ->name('heartbeat-log');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
