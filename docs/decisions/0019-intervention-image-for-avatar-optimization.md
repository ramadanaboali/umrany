# 19. Intervention Image for avatar re-encoding, GD driver

## Status

Accepted

## Context

Avatar upload previously stored whatever the client uploaded verbatim (`Storage::disk('public')`,
no processing) — the spec requires uploaded images be "automatically optimized" and that the
maximum upload size be genuinely configurable rather than just a validation-time file-size check
that still lets an oversized, unoptimized original get stored. `spatie/laravel-medialibrary` is
already in the stack for other purposes but is deliberately not used for avatars (per the existing
`Modules/Core/CLAUDE.md` note: avatars don't need conversions/responsive variants, plain disk
storage is simpler and sufficient) — so re-encoding needed a lighter-weight image library, not a
switch to MediaLibrary.

## Decision

- `intervention/image-laravel` (v4.x) + the GD driver (already compiled into the app image, no
  Dockerfile change needed). `ProfileService::updateAvatar()` decodes the upload with
  `ImageManager::decodePath($file->getRealPath())` (v4's actual API — **not** `read()`, which does
  not exist on this installed version despite being a common assumption carried over from
  Intervention v2-style APIs; confirmed by direct vendor source inspection, not documentation
  guesswork), resizes/crops via `cover($width, $height)`, and re-encodes via
  `encode(new WebpEncoder(quality: $quality))` before storing.
- Every avatar is re-encoded to the same configured format/dimensions/quality
  (`core.avatar.{width,height,format,quality,max_kilobytes}`) regardless of what was uploaded — a
  1200×1200 PNG and a 4000×3000 JPEG both end up as the same fixed-size WebP. This also strips
  EXIF/GPS metadata as a side effect of the re-encode, which is a meaningful (if secondary) privacy
  win worth noting for anyone wondering why metadata isn't preserved.
- `core.avatar.max_kilobytes` now genuinely bounds the *stored* file (post re-encode), not just
  the raw upload accepted by validation — the spec's "maximum upload size shall be configurable"
  requirement is satisfied by the actual stored artifact, not merely the request-time check.

## Consequences

- Every avatar upload now costs one GD decode/resize/encode cycle server-side — an accepted,
  expected cost for genuinely fixed-size, format-normalized output (also means every stored avatar
  has a predictable size/shape for any consumer, e.g. a future CDN/cache layer).
- Anyone touching this code again should verify the actual installed Intervention Image major
  version's real method names against vendor source before assuming an API shape from memory or
  from a different major version's docs — this project got bitten by exactly that once already.
