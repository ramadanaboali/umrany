---
name: laravel-job
description: Create a queued Job wired to the correct Horizon supervisor/queue for its module, with a Pest test. Use whenever work should move off the request cycle (emails, AI calls, settlement, notifications, exports).
---

# Create a queued job

## 1. Does this need a queue at all?

Octane workers are long-lived — don't reach for a queue just to "be async" if the work is sub-100ms. Queue it when the work is slow (external API/AI calls, file processing, report generation), needs retries, or must survive a deploy/restart.

## 2. Generate it in the owning module

```bash
docker compose exec app php artisan module:make-job <Name>Job <Module>
```

## 3. Queue naming (Horizon)

Set `public string $queue` (or `->onQueue(...)` at dispatch) to `<module-alias>-<priority>`, matching root `CLAUDE.md`'s convention:

- `<module>-high` — user-facing, latency-sensitive (e.g. `ecommerce-high` for order confirmation emails)
- `<module>-default` — normal background work
- `<module>-low` — batch/report/cleanup work, AI calls that aren't blocking a UI

Check `config/horizon.php` — every module-prefixed queue used here must have a supervisor entry, or jobs will sit unprocessed. If you're introducing a new `<module>-<priority>` combination, add the supervisor block for it (copy an existing one, adjust `queue` and `balance`/`maxProcesses`).

## 4. Writing the job

- `declare(strict_types=1)`, implement `ShouldQueue`.
- Constructor takes IDs, not models — Eloquent models serialize into the queue payload with stale state; rehydrate inside `handle()`.
- Set `$tries` and `$backoff` deliberately — don't leave Laravel's defaults unexamined for anything calling an external API (AI provider, payment gateway, government API).
- If this job touches another module's data, do it through that module's `Contracts` interface — the cross-module rule applies inside jobs too.
- Cache anything expensive the job recomputes using the `umrany:<module>:<entity>:<id>` key convention.

## 5. Test it

```bash
docker compose exec app ./vendor/bin/pest --filter=<Name>JobTest
```

Use `Queue::fake()` to assert dispatch from the triggering code path, and a separate test that calls `handle()` directly (or `Bus::dispatchSync`) to verify the job's actual logic — faking the queue alone doesn't prove `handle()` works.

## 6. Verify it actually runs

```bash
docker compose exec app php artisan horizon:status
docker compose logs -f horizon
```
Dispatch it once locally and confirm it appears under the right supervisor/queue in the Horizon dashboard (`/horizon`), not stuck in `default`.
