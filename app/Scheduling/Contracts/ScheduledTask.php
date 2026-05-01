<?php

declare(strict_types=1);

namespace App\Scheduling\Contracts;

interface ScheduledTask
{
    public function everyMinute(): self;

    public function everyFiveMinutes(): self;

    public function everyFifteenMinutes(): self;

    public function hourly(): self;

    public function hourlyAt(int $minute): self;

    public function daily(): self;

    /**
     * @param  string  $time  HH:MM
     */
    public function dailyAt(string $time): self;

    public function weekly(): self;

    public function monthly(): self;

    public function cron(string $expression): self;

    public function timezone(string $timezone): self;

    public function withoutOverlapping(int $expiresAtSeconds = 3600): self;

    public function onOneServer(): self;

    public function name(string $name): self;
}
