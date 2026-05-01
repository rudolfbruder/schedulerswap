# schedulerswap

A small Laravel 13 demo that swaps its task scheduler at runtime via an env var. Built as an interview piece to show two things in one repo:

1. **Coding against a contract.** All scheduling goes through `App\Scheduling\Contracts\Scheduler`. Application code never touches Laravel's `Schedule` facade or a Crunz class directly.
2. **Picking the implementation in the container.** `SchedulingServiceProvider::register()` does a `match` on `config('scheduler.type')` and binds one of two adapters. Flip the env var, get a different engine — same call sites, same tests.

```
SCHEDULER_TYPE=laravel   # delegates to Illuminate\Console\Scheduling\Schedule
SCHEDULER_TYPE=crunz     # hand-rolled Crunz-style adapter (no external package)
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

**Shell 2 — scheduler daemon** (ticks every minute, dispatches due tasks through whichever engine is bound):

```bash
php artisan scheduler:work
```

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
    ├── Crunz/               ← Crunz-style runner, dispatches via the bus
    └── Cache/CacheMutex.php ← Mutex impl over Laravel cache locks
```

Both adapters resolve `Mutex` from the container, so swapping the lock store (database, Redis, Memcached, DynamoDB) is a config change, not a code change.

A long-running `scheduler:work` daemon ticks once a minute and calls `Scheduler::runDue($now)` — the same loop drives both engines.

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
- Tailwind v4 + Vite 8 (frontend is the default Laravel welcome page; this demo is backend-only)
- SQLite for app data, cache, sessions, queue, jobs (`DB_CONNECTION=sqlite`, all `*_DRIVER=database`)
- [Laravel Boost](https://laravel.com/docs/ai) MCP server enabled for agent-assisted development

## Scope notes

Portfolio piece, not a production scheduler. Multi-host coordination, fencing tokens, distributed-lock edge cases, and full feature parity with Laravel's scheduler are intentionally out of scope. The point is the seam, not the cluster.
