<?php

declare(strict_types=1);

namespace App\Scheduling\Adapters\Crunz;

use App\Scheduling\Contracts\Mutex;
use App\Scheduling\Contracts\ScheduledTask;
use Closure;
use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

final class CrunzScheduledTask implements ScheduledTask
{
    private string $cronExpression = '* * * * *';

    private string $timezone = 'UTC';

    private ?string $taskName = null;

    private ?int $overlapTtl = null;

    private bool $onOneServer = false;

    public function __construct(
        private readonly Closure $callback,
        private readonly Mutex $mutex,
        private readonly LoggerInterface $logger,
    ) {}

    public function everyMinute(): self
    {
        $this->cronExpression = '* * * * *';

        return $this;
    }

    public function everyFiveMinutes(): self
    {
        $this->cronExpression = '*/5 * * * *';

        return $this;
    }

    public function everyFifteenMinutes(): self
    {
        $this->cronExpression = '*/15 * * * *';

        return $this;
    }

    public function hourly(): self
    {
        $this->cronExpression = '0 * * * *';

        return $this;
    }

    public function hourlyAt(int $minute): self
    {
        $this->cronExpression = "{$minute} * * * *";

        return $this;
    }

    public function daily(): self
    {
        $this->cronExpression = '0 0 * * *';

        return $this;
    }

    public function dailyAt(string $time): self
    {
        [$h, $m] = explode(':', $time);
        $this->cronExpression = "{$m} {$h} * * *";

        return $this;
    }

    public function weekly(): self
    {
        $this->cronExpression = '0 0 * * 0';

        return $this;
    }

    public function monthly(): self
    {
        $this->cronExpression = '0 0 1 * *';

        return $this;
    }

    public function cron(string $expression): self
    {
        new CronExpression($expression);
        $this->cronExpression = $expression;

        return $this;
    }

    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function name(string $name): self
    {
        $this->taskName = $name;

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

    public function runIfDue(DateTimeImmutable $now): void
    {
        $cron = new CronExpression($this->cronExpression);
        $nowInTz = $now->setTimezone(new DateTimeZone($this->timezone));

        if (! $cron->isDue($nowInTz)) {
            return;
        }

        $key = $this->lockKey();
        $needsLock = $this->overlapTtl !== null || $this->onOneServer;

        if ($needsLock) {
            $ttl = $this->overlapTtl ?? 60;
            if (! $this->mutex->acquire($key, $ttl)) {
                $this->logger->info('Scheduler task skipped (locked)', [
                    'task' => $this->taskName,
                    'key' => $key,
                ]);

                return;
            }
        }

        try {
            ($this->callback)();
        } catch (Throwable $e) {
            $this->logger->error('Scheduler task failed', [
                'task' => $this->taskName,
                'exception' => $e,
            ]);
        } finally {
            if ($needsLock) {
                $this->mutex->release($key);
            }
        }
    }

    private function lockKey(): string
    {
        return 'scheduler:'.($this->taskName ?? sha1($this->cronExpression));
    }
}
