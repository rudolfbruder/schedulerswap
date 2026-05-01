<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scheduler Engine
    |--------------------------------------------------------------------------
    |
    | Which scheduler implementation to use. Both speak the same fluent API
    | as defined in App\Scheduling\Contracts\Scheduler.
    |
    | Supported: "laravel", "crunz"
    |
    */
    'type' => env('SCHEDULER_TYPE', 'laravel'),

    /*
    |--------------------------------------------------------------------------
    | Mutex Cache Store
    |--------------------------------------------------------------------------
    |
    | Which cache store (from config/cache.php) the CacheMutex uses for its
    | lock backend. Null = the default cache store. The chosen store must
    | support cache locks (database, redis, memcached, dynamodb).
    |
    */
    'mutex_cache_store' => env('SCHEDULER_MUTEX_STORE'),
];
