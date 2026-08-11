# 3. Scribe over l5-swagger for API Documentation

## Status

Accepted

## Context

The stated requirement was "Swagger" documentation for the API. With five `nwidart/laravel-modules` modules, each owning its own `routes/api.php`, `darkaonline/l5-swagger`'s approach — scanning directories for PHPDoc OpenAPI annotations — requires manually keeping its `paths.annotations` config array in sync with every module's controller directory as modules are added or moved. Its output is also only as accurate as those hand-written annotations stay in sync with the actual code, which is a real drift risk across modules and teams over time.

## Decision

Use `knuckleswtf/scribe` instead of `darkaonline/l5-swagger`. Scribe still produces OpenAPI 3 output, so it satisfies the underlying "Swagger" requirement through a different generation mechanism. Rather than scanning annotated directories, Scribe introspects the actual registered Laravel route list (`Route::getRoutes()`) plus lightweight `@group`/`@bodyParam`/`@response` doc-blocks. This makes it structurally agnostic to which module directory a route lives in. Verified this session: the default `config('scribe.routes.match.prefixes') = ['api/*']` already matched all five modules' routes with zero per-module configuration needed.

## Consequences

- Docs stay closer to actual route behavior, since they're derived from the live route list and real request/response examples rather than hand-maintained annotations that can silently drift.
- Engineers still must write accurate `@bodyParam` and `@response` doc-blocks per controller method for the generated docs to be useful — Scribe introspects routes automatically, but it doesn't invent parameter or response documentation from nothing.
- Adding a sixth module requires no Scribe config changes, since matching is prefix-based against `api/*`, not directory-based.
