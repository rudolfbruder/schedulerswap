<?php

declare(strict_types=1);

namespace App\Scheduling\Contracts;

interface Mutex
{
    /**
     * Try to acquire a lock. Non-blocking. Returns true on success.
     */
    public function acquire(string $key, int $ttlSeconds): bool;

    /**
     * Release a lock previously acquired by THIS process.
     * Implementations must verify ownership (e.g. via fencing token)
     * to avoid releasing someone else's lock.
     */
    public function release(string $key): void;

    /**
     * Check existence without acquiring.
     */
    public function exists(string $key): bool;
}
