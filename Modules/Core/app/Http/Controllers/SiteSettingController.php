<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Resources\SiteSettingResource;
use Modules\Core\Models\SiteSetting;

/**
 * Public, unauthenticated — logo/branding/contact info is exactly the kind of thing every other
 * client (mobile app, marketing site) needs to render a footer/contact page, the same "public
 * read-only platform data" shape as MasterDataController's country/city/currency endpoints.
 * Kept as its own controller rather than folded into MasterDataController: that one is static
 * reference data, this is a single mutable admin-managed resource — a different concern.
 */
#[Group('Core / Site settings', weight: 5)]
final class SiteSettingController extends Controller
{
    /**
     * Show site settings
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => new SiteSettingResource(SiteSetting::current()),
        ]);
    }
}
