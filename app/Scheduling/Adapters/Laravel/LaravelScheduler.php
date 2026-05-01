<?php

declare(strict_types=1);

namespace App\Scheduling\Adapters\Laravel;

use App\Scheduling\Contracts\ScheduledTask;
use App\Scheduling\Contracts\Scheduler;
use DateTimeImmutable;
use Illuminate\Console\Scheduling\Schedule as LaravelSchedule;
use Illuminate\Contracts\Console\Kernel as ArtisanKernel;

final class LaravelScheduler implements Scheduler
{
    public function __construct(
        private readonly LaravelSchedule $schedule,
        private readonly ArtisanKernel $artisan,
    ) {}

    public function job(object $job): ScheduledTask
    {
        return new LaravelScheduledTask($this->schedule->job($job));
    }

    public function command(string $command, array $parameters = []): ScheduledTask
    {
        return new LaravelScheduledTask($this->schedule->command($command, $parameters));
    }

    public function call(callable $callback): ScheduledTask
    {
        return new LaravelScheduledTask($this->schedule->call($callback));
    }

    /**
     * Delegates to Laravel's `schedule:run` so the same scheduler:work daemon
     * works regardless of engine.
     */
    public function runDue(DateTimeImmutable $now): void
    {
        $this->artisan->call('schedule:run');
    }
}
