<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Scheduling\Contracts\Scheduler;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Throwable;

final class SchedulerWorkCommand extends Command
{
    protected $signature = 'scheduler:work';

    protected $description = 'Long-running scheduler daemon — ticks every minute regardless of engine.';

    public function handle(Scheduler $scheduler): int
    {
        // Force Laravel to invoke withSchedule() callbacks so tasks register
        // on the portable Scheduler. No-op for laravel engine, required for crunz.
        $this->laravel->make(Schedule::class);

        $this->info('Scheduler daemon started. Engine: '.config('scheduler.type'));

        $this->tick($scheduler);

        while (true) {
            $now = new DateTimeImmutable('now');
            $sleepSeconds = 60 - (int) $now->format('s');
            sleep($sleepSeconds);

            $this->tick($scheduler);
        }

        return self::SUCCESS;
    }

    private function tick(Scheduler $scheduler): void
    {
        try {
            $scheduler->runDue(new DateTimeImmutable('now'));
        } catch (Throwable $e) {
            $this->error("Scheduler tick failed: {$e->getMessage()}");
            report($e);
        }
    }
}
