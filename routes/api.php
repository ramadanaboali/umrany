<?php

// Intentionally empty — every module owns its own versioned routes/api.php
// (Route::prefix('v1/<alias>')), registered via that module's RouteServiceProvider. See
// docs/architecture/module-boundaries.md for how routes are mounted per module, and
// tests/Feature/ApiVersioningTest.php for the standing check that every API route stays
// versioned. This file previously held Laravel's default unauthenticated-user stub route
// (GET /api/user), removed as dead/unversioned code — confirmed unused anywhere in the app.
