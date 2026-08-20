<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Requests\Profile\UpdateAvatarRequest;
use Modules\Core\Http\Requests\Profile\UpdateCurrencyRequest;
use Modules\Core\Http\Requests\Profile\UpdateLanguageRequest;
use Modules\Core\Http\Requests\Profile\UpdateProfileRequest;
use Modules\Core\Http\Resources\ProfileResource;
use Modules\Core\Services\ProfileService;

#[Group('Core / Profile', weight: 2)]
final class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profiles,
    ) {}

    /**
     * View profile
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new ProfileResource($request->user())]);
    }

    /**
     * Update profile
     *
     * Changing email or mobile clears that channel's verified status and issues a new
     * verification code — see docs/modules/core.md § Profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profiles->updateProfile($request->user(), $request->validated());

        return response()->json(['data' => new ProfileResource($user)]);
    }

    /**
     * Upload/replace avatar
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $this->profiles->updateAvatar($request->user(), $request->file('avatar'));

        return response()->json(['data' => new ProfileResource($request->user()->refresh())]);
    }

    /**
     * Remove avatar
     */
    public function destroyAvatar(Request $request): JsonResponse
    {
        $this->profiles->removeAvatar($request->user());

        return response()->json(null, 204);
    }

    /**
     * Set language preference
     *
     * FR-PROFILE-005 — the client is expected to switch RTL/LTR based on the returned value.
     */
    public function updateLanguage(UpdateLanguageRequest $request): JsonResponse
    {
        $this->profiles->updateLanguage($request->user(), $request->string('language')->value());

        return response()->json(['data' => new ProfileResource($request->user()->refresh())]);
    }

    /**
     * Set currency preference
     */
    public function updateCurrency(UpdateCurrencyRequest $request): JsonResponse
    {
        $this->profiles->updateCurrency($request->user(), $request->integer('currency_id'));

        return response()->json(['data' => new ProfileResource($request->user()->refresh())]);
    }
}
