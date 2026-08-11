# 4. phpredis over predis

## Status

Accepted

## Context

Redis backs both the application cache store and the Horizon-managed queue, under significant expected job and cache throughput: chat, notifications, the AI job queue, and marketplace order processing all move through it. Laravel supports two Redis client implementations: `predis`, a pure-PHP package with no extension dependency, and `phpredis`, a C extension installed via PECL. phpredis benchmarks meaningfully faster — roughly 2-5x in cited comparisons — than predis for the GET/SET-heavy access patterns that Horizon and cache usage produce, since it avoids per-call userland PHP overhead that a pure-PHP client carries.

## Decision

Use the phpredis PECL extension as the Redis client (`REDIS_CLIENT=phpredis` in `.env`), with igbinary enabled as the serializer for more compact and faster (de)serialization of cached and queued PHP values.

## Consequences

- phpredis is not present in base PHP Docker images and must be compiled in via PECL (`pecl install redis`, plus the `igbinary` extension). This is a one-time Dockerfile cost, already paid in `docker/app/Dockerfile`.
- predis remains preferable only in environments where installing PHP extensions isn't possible, e.g. some shared hosting — not applicable here, since the team controls the Docker image end to end.
- Any future environment or deployment target that can't run PECL extensions would need this decision revisited; there is currently no fallback path to predis configured.
