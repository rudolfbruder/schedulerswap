<?php

declare(strict_types=1);

namespace App\Scheduling\Adapters\Cache;

use App\Scheduling\Contracts\Mutex;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Mutex backed by Laravel's cache lock store (cache_locks table for the
 * database driver). Uses owner tokens for fencing semantics — release only
 * deletes the row if the calling process is still the owner.
 */
final class CacheMutex implements Mutex
{
    /** @var array<string, string> Per-process map of key => owner token */
    private array $owners = [];

    public function __construct(
        private readonly CacheFactory $cache,
        private readonly ?string $store = null,
    ) {}

    public function acquire(string $key, int $ttlSeconds): bool
    {
        $owner = bin2hex(random_bytes(16));

        $acquired = $this->store()->lock($key, $ttlSeconds, $owner)->get();

        if ($acquired) {
            $this->owners[$key] = $owner;

            return true;
        }

        return false;
    }

    public function release(string $key): void
    {
        if (! isset($this->owners[$key])) {
            return;
        }

        try {
            $this->store()->restoreLock($key, $this->owners[$key])->release();
        } finally {
            unset($this->owners[$key]);
        }
    }

    public function exists(string $key): bool
    {
        $probe = $this->store()->lock($key, 1, bin2hex(random_bytes(8)));

        if ($probe->get()) {
            $probe->release();

            return false;
        }

        return true;
    }

    private function store(): CacheRepository
    {
        return $this->cache->store($this->store);
    }
}
