#!/bin/bash
set -euo pipefail

# Do NOT run this file directly (e.g. via an IDE's "run script" button) — it is
# only meant to be executed BY the official postgres image's entrypoint, inside
# the `umrany_postgres` container, on first init of a fresh data volume (see
# docs/decisions/0006-dedicated-postgres-schema.md). Run outside that context —
# on your host machine, or manually without the container's env — POSTGRES_USER/
# POSTGRES_DB won't be set and this fails fast with a clear message below rather
# than a cryptic "unbound variable" error.
: "${POSTGRES_USER:?POSTGRES_USER is not set — this script must run inside the postgres container's docker-entrypoint-initdb.d, not standalone}"
: "${POSTGRES_DB:?POSTGRES_DB is not set — this script must run inside the postgres container's docker-entrypoint-initdb.d, not standalone}"

# Creates the app's dedicated schema instead of relying on the "public" schema.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE SCHEMA IF NOT EXISTS umrany AUTHORIZATION "$POSTGRES_USER";
EOSQL
