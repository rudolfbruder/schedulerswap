<?php

declare(strict_types=1);

namespace App\Providers;

use App\Scheduling\Adapters\Cache\CacheMutex;
use App\Scheduling\Adapters\Crunz\CrunzScheduler;
use App\Scheduling\Adapters\Laravel\LaravelScheduler;
use App\Scheduling\Contracts\Mutex;
use App\Scheduling\Contracts\Scheduler;
use Illuminate\Console\Scheduling\Schedule as LaravelSchedule;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Console\Kernel as ArtisanKernel;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Mutex::class, function ($app) {
            return new CacheMutex(
                $app->make(CacheFactory::class),
                $app['config']->get('scheduler.mutex_cache_store'),
            );
        });

        $this->app->singleton(Scheduler::class, function ($app) {
            $type = $app['config']->get('scheduler.type', 'laravel');

            return match ($type) {
                'laravel' => new LaravelScheduler(
                    $app->make(LaravelSchedule::class),
                    $app->make(ArtisanKernel::class),
                ),
                'crunz' => new CrunzScheduler(
                    $app->make(Mutex::class),
                    $app->make(Dispatcher::class),
                    $app->make(ArtisanKernel::class),
                    $app->make(LoggerInterface::class),
                ),
                default => throw new InvalidArgumentException(
                    "Unknown scheduler type: {$type}. Expected 'laravel' or 'crunz'."
                ),
            };
        });
    }
}
