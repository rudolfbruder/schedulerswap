<?php

declare(strict_types=1);

namespace App\Scheduling\Contracts;

use DateTimeImmutable;

interface Scheduler
{
    /**
     * Schedule a queueable job (an object dispatchable via the bus).
     */
    public function job(object $job): ScheduledTask;

    /**
     * Schedule an Artisan-style command by name.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function command(string $command, array $parameters = []): ScheduledTask;

    /**
     * Schedule an arbitrary callable.
     */
    public function call(callable $callback): ScheduledTask;

    /**
     * Run all due tasks at the given moment.
     * Called by the supervisor-driven scheduler:work daemon every minute.
     */
    public function runDue(DateTimeImmutable $now): void;
}
