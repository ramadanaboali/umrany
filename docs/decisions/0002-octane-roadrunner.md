# 2. Octane on RoadRunner (not Swoole or FrankenPHP)

## Status

Accepted

## Context

The platform's real-time and queue-heavy features — chat, notifications, AI calls — need a high-performance persistent-worker PHP runtime rather than a traditional PHP-FPM per-request bootstrap, where the framework is rebuilt from scratch on every request. `laravel/octane` provides this by keeping the framework booted in long-lived workers. Octane supports multiple application servers; the choice was between Swoole, FrankenPHP, and RoadRunner.

Swoole requires a PECL extension with a narrower maintenance footprint. FrankenPHP is newer, single-binary, but had less production track record at the time of this decision. RoadRunner runs as a standalone Go binary that speaks a worker protocol to PHP rather than being a PHP extension itself.

## Decision

Run the application via `laravel/octane` with the RoadRunner application server, chosen over Swoole and FrankenPHP for team familiarity and production-proven stability as a standalone binary.

## Consequences

- Persistent workers mean state must not leak across requests: container bindings must not be registered as `singleton()` when they carry request-scoped or container-registration-order-sensitive state — use `scoped()` instead. This is the same gotcha noted in ADR 0001 regarding nwidart module service providers.
- Route, config, and module state get cached into the worker process. Toggling a module or changing module config requires `php artisan octane:reload` to pick up the change — `cache:clear` alone is not sufficient, since it doesn't restart the already-booted workers.
- RoadRunner being a separate Go binary (not a PHP extension) keeps the Docker image simpler and avoids coroutine-compatibility gotchas that Swoole can introduce for ordinary synchronous PHP/Composer packages.
