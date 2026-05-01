<?php

declare(strict_types=1);

namespace App\Scheduling\Adapters\Crunz;

use App\Scheduling\Contracts\Mutex;
use App\Scheduling\Contracts\ScheduledTask;
use Closure;
use Crunz\Event as CrunzEvent;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Wraps a Crunz Event for cron expression / due-time evaluation, but executes
 * the user callback in the host Laravel process — not via Crunz's
 * subprocess-based Event::start(). That keeps the bus, container, and config
 * available to the queued job or artisan command we are running.
 */
final class CrunzScheduledTask implements ScheduledTask
{
    private DateTimeZone $timezone;

    private ?int $overlapTtl = null;

    private bool $onOneServer = false;

    private ?string $taskName = null;

    public function __construct(
        private readonly CrunzEvent $event,
        private readonly Closure $callback,
    ) {
        $this->timezone = new DateTimeZone(date_default_timezone_get());
    }

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
        $this->timezone = new DateTimeZone($timezone);
        $this->event->timezone($this->timezone);

        return $this;
    }

    public function withoutOverlapping(int $expiresAtSeconds = 3600): self
    {
        $this->overlapTtl = $expiresAtSeconds;

        return $this;
    }

    public function onOneServer(): self
    {
        $this->onOneServer = true;

        return $this;
    }

    public function name(string $name): self
    {
        $this->taskName = $name;
        $this->event->description($name);

        return $this;
    }

    public function runIfDue(Mutex $mutex, LoggerInterface $logger): void
    {
        if (! $this->event->isDue($this->timezone)) {
            return;
        }

        $needsLock = $this->overlapTtl !== null || $this->onOneServer;

        if ($needsLock) {
            $key = $this->lockKey();
            $ttl = $this->overlapTtl ?? 60;

            if (! $mutex->acquire($key, $ttl)) {
                $logger->info('Scheduler task skipped (locked)', [
                    'task' => $this->taskName,
                    'key' => $key,
                ]);

                return;
            }

            try {
                ($this->callback)();
            } catch (Throwable $e) {
                $logger->error('Scheduler task failed', [
                    'task' => $this->taskName,
                    'exception' => $e,
                ]);
            } finally {
                $mutex->release($key);
            }

            return;
        }

        try {
            ($this->callback)();
        } catch (Throwable $e) {
            $logger->error('Scheduler task failed', [
                'task' => $this->taskName,
                'exception' => $e,
            ]);
        }
    }

    private function lockKey(): string
    {
        return 'scheduler:'.($this->taskName ?? sha1($this->event->getExpression()));
    }
}
