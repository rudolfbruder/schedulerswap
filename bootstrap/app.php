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

        // Non-Laravel engines do not register on Illuminate\Console\Scheduling\Schedule,
        // so cron-driven `schedule:run` would be a no-op. Bridge a one-minute tick
        // through to the portable Scheduler. Skipped for the Laravel engine because
        // its tasks already live on this Schedule and would otherwise recurse.
        if (config('scheduler.type') !== 'laravel') {
            $schedule->call(fn () => $scheduler->runDue(new DateTimeImmutable('now')))
                ->everyMinute()
                ->name('portable-scheduler-tick');
        }
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
