<?php

declare(strict_types=1);

use App\Scheduling\Adapters\Cache\CacheMutex;
use App\Scheduling\Adapters\Crunz\CrunzScheduler;
use App\Scheduling\Adapters\Laravel\LaravelScheduler;
use App\Scheduling\Contracts\Mutex;
use App\Scheduling\Contracts\Scheduler;

it('binds Mutex to CacheMutex', function (): void {
    expect(app(Mutex::class))->toBeInstanceOf(CacheMutex::class);
});

it('resolves laravel engine when SCHEDULER_TYPE=laravel', function (): void {
    config(['scheduler.type' => 'laravel']);
    app()->forgetInstance(Scheduler::class);

    expect(app(Scheduler::class))->toBeInstanceOf(LaravelScheduler::class);
});

it('resolves crunz engine when SCHEDULER_TYPE=crunz', function (): void {
    config(['scheduler.type' => 'crunz']);
    app()->forgetInstance(Scheduler::class);

    expect(app(Scheduler::class))->toBeInstanceOf(CrunzScheduler::class);
});

it('throws on unknown scheduler type', function (): void {
    config(['scheduler.type' => 'bogus']);
    app()->forgetInstance(Scheduler::class);

    app(Scheduler::class);
})->throws(InvalidArgumentException::class);
