# schedulerswap

A small Laravel 13 demo that swaps its task scheduler at runtime via an env var. Built as an interview piece to show two things in one repo:

1. **Coding against a contract.** All scheduling goes through `App\Scheduling\Contracts\Scheduler`. Application code never touches Laravel's `Schedule` facade or a Crunz class directly.
2. **Picking the implementation in the container.** `SchedulingServiceProvider::register()` does a `match` on `config('scheduler.type')` and binds one of two adapters. Flip the env var, get a different engine — same call sites, same tests.

```
SCHEDULER_TYPE=laravel   # delegates to Illuminate\Console\Scheduling\Schedule
SCHEDULER_TYPE=crunz     # delegates to crunzphp/crunz (Crunz\Schedule + Crunz\Event)
```

---

## Requirements

- PHP **8.4+**
- Composer 2
- Node 20+ / npm
- SQLite (the bundled `database/database.sqlite` file is enough)
- [Laravel Herd](https://herd.laravel.com) for local serving — the app expects `http://schedulerswap.test`

No Redis, no MySQL, no external services. Cache, sessions, queue, and jobs all live in SQLite via the `database` driver.

## Install

```bash
# 1. Clone and enter
git clone <repo-url> schedulerswap
cd schedulerswap

# 2. PHP + JS deps
composer install
npm install

# 3. Env + app key
cp .env.example .env
php artisan key:generate

# 4. SQLite file + schema (creates cache, sessions, jobs, failed_jobs tables too)
touch database/database.sqlite
php artisan migrate

# 5. Frontend assets (one-shot build, or use composer dev below for watch mode)
npm run build
```

Herd should auto-detect the directory and serve it at `http://schedulerswap.test`. If not:

```bash
herd link
herd open
```

> Do **not** run `php artisan serve` — Herd already serves the site, and the dev script below assumes it.

## Run

Two long-running processes. Open two shells.

**Shell 1 — dev stack** (Vite watch + queue worker + log tail, all via `concurrently`):

```bash
composer dev
```

**Shell 2 — scheduler tick.** Pick one:

```bash
# Option A: long-running daemon, ticks itself every minute, engine-agnostic
php artisan scheduler:work

# Option B: standard Laravel cron entry / Herd's "Run Scheduler" toggle
* * * * * cd /path/to/schedulerswap && php artisan schedule:run >> /dev/null 2>&1
```

Both paths run the bundled `HeartbeatJob` on either engine. `schedule:run` works on Crunz mode because `bootstrap/app.php` bridges a one-minute tick from Laravel's `Schedule` into the portable `Scheduler` (skipped on `SCHEDULER_TYPE=laravel` to avoid recursion — Laravel-mode tasks already live on `Schedule`).

The bundled `HeartbeatJob` is registered in `bootstrap/app.php` via the portable `Scheduler` interface and runs every minute. Watch the heartbeat:

```bash
php artisan pail
# or: tail -f storage/logs/laravel.log
```

Verify what's bound right now:

```bash
php artisan scheduler:debug
```

That prints the resolved `Scheduler` and `Mutex` classes and runs a live mutex acquire / exists / release round-trip.

---

## Swap the engine

The whole point of the project. Three steps.

### 1. Edit `.env`

```dotenv
# Pick one
SCHEDULER_TYPE=laravel
SCHEDULER_TYPE=crunz

# Optional: which cache store backs the CacheMutex.
# Must support cache locks: database | redis | memcached | dynamodb.
# Leave commented to use the default (database, given CACHE_STORE=database).
# SCHEDULER_MUTEX_STORE=database
```

### 2. Clear config cache

Required if you've ever run `php artisan config:cache`, harmless otherwise:

```bash
php artisan config:clear
```

### 3. Restart the daemon

The container resolves `Scheduler` once at boot, so the running `scheduler:work` process will not pick up the new value until restarted.

```bash
# Ctrl+C the running daemon, then:
php artisan scheduler:work
```

Confirm it flipped:

```bash
php artisan scheduler:debug
# Scheduler engine:    App\Scheduling\Adapters\Crunz\CrunzScheduler
# Mutex implementation: App\Scheduling\Adapters\Cache\CacheMutex
# config(scheduler.type) = crunz
```

An unknown `SCHEDULER_TYPE` value throws `InvalidArgumentException` at the binding site — by design, fail fast at boot rather than silently fall through.

---

## Demo cue

Walk the interview in this order — each step is one short file:

| Step | File | What to point at |
|---|---|---|
| 1 | `app/Scheduling/Contracts/Scheduler.php` | The port. `job()`, `command()`, `call()`, `runDue()`. |
| 2 | `app/Scheduling/Contracts/ScheduledTask.php` | Fluent cron API used by both adapters. |
| 3 | `bootstrap/app.php` → `withSchedule()` | Application registers a job through the **interface**, not the framework facade. |
| 4 | `app/Providers/SchedulingServiceProvider.php` | The `match ($type)` binding — the swap point. |
| 5 | `app/Scheduling/Adapters/Laravel/` & `Adapters/Crunz/` | Two implementations behind the same contract. |
| 6 | `.env` → toggle `SCHEDULER_TYPE`, restart `scheduler:work` | Behavior changes, code unchanged. |
| 7 | `tests/Feature/SchedulerBindingTest.php` | Container hands back the right concrete for each value; unknown value throws. |

## Architecture

```
app/Scheduling/
├── Contracts/
│   ├── Scheduler.php        ← port
│   ├── ScheduledTask.php    ← fluent cron API
│   └── Mutex.php            ← locking port
└── Adapters/
    ├── Laravel/             ← delegates to Illuminate\Console\Scheduling\Schedule
    ├── Crunz/               ← wraps crunzphp/crunz Event for cron + isDue, runs callback in-process
    └── Cache/CacheMutex.php ← Mutex impl over Laravel cache locks
```

A long-running `scheduler:work` daemon ticks once a minute and calls `Scheduler::runDue($now)` — the same loop drives both engines.

### Locking

Both engines route overlap prevention through one contract: `App\Scheduling\Contracts\Mutex` (`acquire / release / exists`). The shipped implementation is `App\Scheduling\Adapters\Cache\CacheMutex`, backed by Laravel's cache locks (`Illuminate\Contracts\Cache\Repository::lock`).

- **Fencing tokens.** Each `acquire()` mints a 16-byte hex owner token, stashes it in a per-process map, and only `restoreLock($key, $owner)->release()` succeeds — a stale or impostor caller cannot release someone else's lock. Verified by `tests/Feature/CacheMutexTest.php`.
- **Store override.** The mutex resolves the cache store from `config('scheduler.mutex_cache_store')` (env: `SCHEDULER_MUTEX_STORE`). Any cache driver that supports locks works: `database`, `redis`, `memcached`, `dynamodb`. Default = `null` = the app's default cache store, which is `database` per `.env`.
- **Same lock across engines.** `Mutex` is a singleton bound in `SchedulingServiceProvider::register()`. Swapping `SCHEDULER_TYPE` between `laravel` and `crunz` does not touch the locking story — both engines see the same `CacheMutex` instance and the same backing store.

### Crunz integration note

The Crunz adapter wraps a real `Crunz\Event` for the fluent cron API and `isDue()` evaluation, but **does not** call `Event::start()` — that path serializes the closure and runs it in a fresh subprocess via `bin/crunz closure:run`, which would lose the Laravel container, bus, and config. The host process invokes the original callback directly.

Crunz's own `Event::preventOverlapping()` (which wants a Symfony `PersistingStoreInterface`) is **also bypassed** for the same reason — it's wired into the `start()` pipeline. Instead, `withoutOverlapping($ttl)` and `onOneServer()` set flags on the `CrunzScheduledTask` wrapper; `runIfDue()` calls our `Mutex` around the callback. Lock key is `'scheduler:'.($name ?? sha1($event->getExpression()))` — task name if set, else hash of the cron expression Crunz computed.

Net split: **Crunz owns cron + due-time math; schedulerswap owns execution and concurrency.**

## Tests

```bash
composer test
# or
php artisan test --compact
# single test:
php artisan test --compact --filter=SchedulerBinding
```

Key coverage:
- `tests/Feature/SchedulerBindingTest.php` — both env values resolve to the right adapter; an unknown value throws.
- `tests/Feature/CacheMutexTest.php` — acquire / exists / release semantics on the cache-backed mutex.

## Stack

- PHP 8.4, Laravel 13, Pest 4
- [`crunzphp/crunz`](https://github.com/crunzphp/crunz) ^3.9 — second scheduler engine
- Tailwind v4 + Vite 8 (frontend is the default Laravel welcome page; this demo is backend-only)
- SQLite for app data, cache, sessions, queue, jobs (`DB_CONNECTION=sqlite`, all `*_DRIVER=database`)
- [Laravel Boost](https://laravel.com/docs/ai) MCP server enabled for agent-assisted development

## Scope notes

Portfolio piece, not a production scheduler. Multi-host coordination, distributed-lock edge cases (clock skew, partial failure mid-run), and full feature parity with Laravel's scheduler are intentionally out of scope. Fencing tokens are implemented for the cache-backed mutex, but the only `Mutex` impl shipped is single-store and process-local in its owner map — durable cross-host fencing would need a different adapter. The point is the seam, not the cluster.
