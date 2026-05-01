<?php

declare(strict_types=1);

namespace App\Scheduling\Adapters\Crunz;

use App\Scheduling\Contracts\Mutex;
use App\Scheduling\Contracts\ScheduledTask;
use App\Scheduling\Contracts\Scheduler;
use Closure;
use Crunz\Schedule as CrunzSchedule;
use DateTimeImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Console\Kernel as ArtisanKernel;
use Psr\Log\LoggerInterface;

final class CrunzScheduler implements Scheduler
{
    private readonly CrunzSchedule $schedule;

    /** @var CrunzScheduledTask[] */
    private array $tasks = [];

    public function __construct(
        private readonly Mutex $mutex,
        private readonly Dispatcher $bus,
        private readonly ArtisanKernel $artisan,
        private readonly LoggerInterface $logger,
    ) {
        $this->schedule = new CrunzSchedule;
    }

    public function job(object $job): ScheduledTask
    {
        return $this->register(fn () => $this->bus->dispatch($job));
    }

    public function command(string $command, array $parameters = []): ScheduledTask
    {
        return $this->register(fn () => $this->artisan->call($command, $parameters));
    }

    public function call(callable $callback): ScheduledTask
    {
        return $this->register(Closure::fromCallable($callback));
    }

    public function runDue(DateTimeImmutable $now): void
    {
        foreach ($this->tasks as $task) {
            $task->runIfDue($this->mutex, $this->logger);
        }
    }

    private function register(Closure $callback): CrunzScheduledTask
    {
        // The closure passed to Schedule::run is a placeholder — Event::start()
        // is never invoked. Crunz only owns the cron expression and isDue()
        // evaluation; the host process runs the real callback.
        $event = $this->schedule->run(static fn () => null);
        $task = new CrunzScheduledTask($event, $callback);
        $this->tasks[] = $task;

        return $task;
    }
}
