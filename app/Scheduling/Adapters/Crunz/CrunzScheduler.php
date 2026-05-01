<?php

declare(strict_types=1);

namespace App\Scheduling\Adapters\Crunz;

use App\Scheduling\Contracts\Mutex;
use App\Scheduling\Contracts\ScheduledTask;
use App\Scheduling\Contracts\Scheduler;
use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Console\Kernel as ArtisanKernel;
use Psr\Log\LoggerInterface;

final class CrunzScheduler implements Scheduler
{
    /** @var CrunzScheduledTask[] */
    private array $tasks = [];

    public function __construct(
        private readonly Mutex $mutex,
        private readonly Dispatcher $bus,
        private readonly ArtisanKernel $artisan,
        private readonly LoggerInterface $logger,
    ) {}

    public function job(object $job): ScheduledTask
    {
        $task = new CrunzScheduledTask(
            fn () => $this->bus->dispatch($job),
            $this->mutex,
            $this->logger,
        );
        $this->tasks[] = $task;

        return $task;
    }

    public function command(string $command, array $parameters = []): ScheduledTask
    {
        $task = new CrunzScheduledTask(
            fn () => $this->artisan->call($command, $parameters),
            $this->mutex,
            $this->logger,
        );
        $this->tasks[] = $task;

        return $task;
    }

    public function call(callable $callback): ScheduledTask
    {
        $task = new CrunzScheduledTask(
            Closure::fromCallable($callback),
            $this->mutex,
            $this->logger,
        );
        $this->tasks[] = $task;

        return $task;
    }

    public function runDue(DateTimeImmutable $now): void
    {
        foreach ($this->tasks as $task) {
            $task->runIfDue($now);
        }
    }
}
