# 6. Dedicated Postgres schema instead of `public`

## Status

Accepted

## Context

Postgres creates a `public` schema by default in every new database, and most tutorials/examples leave application tables there. That's fine for a single-purpose database, but conflates "the database" with "the app's tables" — anything else that might ever need to live in the same physical `umrany` database (a future reporting/analytics role with read access, an extension, a second app sharing the instance) has no clean boundary from application tables, and `public` carries broader default grants (historically world-writable in older Postgres versions) than a purpose-created schema does.

## Decision

Application tables live in a dedicated `umrany` schema inside the `umrany` database, not `public`. This is set up in two places:

- `docker/postgres/init/01-create-schema.sh` — runs automatically via Postgres's official image `/docker-entrypoint-initdb.d` mechanism, **once**, only when the data volume is freshly initialized: `CREATE SCHEMA IF NOT EXISTS umrany AUTHORIZATION "$POSTGRES_USER"`.
- `config/database.php`'s `pgsql` connection: `'search_path' => env('DB_SCHEMA', 'umrany')`, with `DB_SCHEMA=umrany` set in `.env`/`.env.example`. Laravel's Postgres connector runs `SET search_path TO ...` using this value on every connection, so all unqualified table references (every migration, every Eloquent model) resolve into `umrany`, not `public`.

## Consequences

- Because `/docker-entrypoint-initdb.d` scripts only run on first init of an empty data directory, this only takes effect on a **fresh** `postgres_data` volume. An existing volume that already has a `public`-based schema needs either a manual `CREATE SCHEMA umrany;` + moved tables, or (simpler, since this is still a data-free skeleton) `docker compose down -v` on the `postgres` volume and let it reinitialize.
- `pg_catalog` (Postgres's system catalog) is always implicitly searched regardless of `search_path`, so this doesn't affect system functions/types — only where application tables land.
- Any one-off `psql`/GUI-client session against this database needs `SET search_path TO umrany;` (or `-c 'search_path=umrany'` on the connection) to see the app's tables without schema-qualifying every query — `\dt` alone will show `public` (empty) unless you do this.
- If a future need arises for a second schema in the same database (e.g., a read-only reporting role), this same pattern (its own `docker-entrypoint-initdb.d` script, its own `search_path` per role/connection) extends cleanly — nothing about this decision assumes only one schema will ever exist.
