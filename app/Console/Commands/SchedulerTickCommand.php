<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Scheduling\Contracts\Scheduler;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;

final class SchedulerTickCommand extends Command
{
    protected $signature = 'scheduler:tick';

    protected $description = 'Run all due tasks once via the portable Scheduler. Engine-agnostic — works for laravel and crunz.';

    public function handle(Scheduler $scheduler): int
    {
        // Force Laravel to invoke withSchedule() callbacks so tasks register
        // on the portable Scheduler. No-op for laravel engine, required for crunz.
        $this->laravel->make(Schedule::class);

        $now = new DateTimeImmutable('now');
        $this->info('Ticking scheduler ('.config('scheduler.type').") at {$now->format('Y-m-d H:i:s')}");

        $scheduler->runDue($now);

        $this->info('Tick complete.');

        return self::SUCCESS;
    }
}
