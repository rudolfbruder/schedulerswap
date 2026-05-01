<?php

declare(strict_types=1);

namespace App\Scheduling\Adapters\Laravel;

use App\Scheduling\Contracts\ScheduledTask;
use Illuminate\Console\Scheduling\Event as LaravelEvent;

final class LaravelScheduledTask implements ScheduledTask
{
    public function __construct(private readonly LaravelEvent $event) {}

    public function everyMinute(): self
    {
        $this->event->everyMinute();

        return $this;
    }

    public function everyFiveMinutes(): self
    {
        $this->event->everyFiveMinutes();

        return $this;
    }

    public function everyFifteenMinutes(): self
    {
        $this->event->everyFifteenMinutes();

        return $this;
    }

    public function hourly(): self
    {
        $this->event->hourly();

        return $this;
    }

    public function hourlyAt(int $minute): self
    {
        $this->event->hourlyAt($minute);

        return $this;
    }

    public function daily(): self
    {
        $this->event->daily();

        return $this;
    }

    public function dailyAt(string $time): self
    {
        $this->event->dailyAt($time);

        return $this;
    }

    public function weekly(): self
    {
        $this->event->weekly();

        return $this;
    }

    public function monthly(): self
    {
        $this->event->monthly();

        return $this;
    }

    public function cron(string $expression): self
    {
        $this->event->cron($expression);

        return $this;
    }

    public function timezone(string $timezone): self
    {
        $this->event->timezone($timezone);

        return $this;
    }

    public function withoutOverlapping(int $expiresAtSeconds = 3600): self
    {
        $minutes = max(1, (int) ceil($expiresAtSeconds / 60));
        $this->event->withoutOverlapping($minutes);

        return $this;
    }

    public function onOneServer(): self
    {
        $this->event->onOneServer();

        return $this;
    }

    public function name(string $name): self
    {
        $this->event->name($name);

        return $this;
    }
}
