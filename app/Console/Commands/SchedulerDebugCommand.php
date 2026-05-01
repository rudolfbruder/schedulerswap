<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Scheduling\Contracts\Mutex;
use App\Scheduling\Contracts\Scheduler;
use Illuminate\Console\Command;

final class SchedulerDebugCommand extends Command
{
    protected $signature = 'scheduler:debug';

    protected $description = 'Print active scheduler/mutex bindings and run a lock test.';

    public function handle(Scheduler $scheduler, Mutex $mutex): int
    {
        $this->info('Scheduler engine:    '.$scheduler::class);
        $this->info('Mutex implementation: '.$mutex::class);
        $this->info('config(scheduler.type) = '.config('scheduler.type'));
        $this->newLine();

        $this->info('Mutex acquire/release test...');
        $key = 'scheduler:debug:'.uniqid();

        $acquired = $mutex->acquire($key, 5);
        $this->line($acquired ? '  [ok] acquired' : '  [fail] failed to acquire');

        if ($acquired) {
            $exists = $mutex->exists($key);
            $this->line($exists ? '  [ok] exists check passes' : '  [fail] exists check FAILED');

            $mutex->release($key);
            $this->line('  [ok] released');

            $stillExists = $mutex->exists($key);
            $this->line($stillExists ? '  [fail] still exists after release!' : '  [ok] gone after release');
        }

        return self::SUCCESS;
    }
}
