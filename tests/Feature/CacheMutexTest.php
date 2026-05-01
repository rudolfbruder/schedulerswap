<?php

declare(strict_types=1);

use App\Scheduling\Contracts\Mutex;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('acquires, blocks duplicates, releases, and re-acquires', function (): void {
    $mutex = app(Mutex::class);
    $key = 'test:mutex:'.uniqid();

    expect($mutex->acquire($key, 10))->toBeTrue();
    expect($mutex->acquire($key, 10))->toBeFalse();
    expect($mutex->exists($key))->toBeTrue();

    $mutex->release($key);
    expect($mutex->exists($key))->toBeFalse();

    expect($mutex->acquire($key, 10))->toBeTrue();
    $mutex->release($key);
});

it('does not release a lock owned by another process (fencing token)', function (): void {
    $mutex = app(Mutex::class);
    $key = 'test:fencing:'.uniqid();

    expect($mutex->acquire($key, 10))->toBeTrue();

    app()->forgetInstance(Mutex::class);
    $other = app(Mutex::class);

    $other->release($key);
    expect($mutex->exists($key))->toBeTrue();

    $mutex->release($key);
    expect($mutex->exists($key))->toBeFalse();
});
