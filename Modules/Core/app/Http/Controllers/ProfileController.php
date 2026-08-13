<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Requests\Profile\UpdateAvatarRequest;
use Modules\Core\Http\Requests\Profile\UpdateCurrencyRequest;
use Modules\Core\Http\Requests\Profile\UpdateLanguageRequest;
use Modules\Core\Http\Requests\Profile\UpdateProfileRequest;
use Modules\Core\Http\Resources\ProfileResource;
use Modules\Core\Services\ProfileService;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profiles,
    ) {}

    /**
     * View profile
     *
     * @group Core / Profile
     *
     * @authenticated
     *
     * @response 200 scenario="success" {"data": {"id": 1, "full_name": "Ahmed Al-Otaibi", "email": "ahmed@example.com"}}
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
     *
     * @group Core / Profile
     *
     * @authenticated
     *
     * @bodyParam full_name string Example: Ahmed Al-Otaibi
     * @bodyParam email string Example: ahmed@example.com
     * @bodyParam mobile string Example: +966501234567
     * @bodyParam address string Example: King Fahd Road
     * @bodyParam country_id integer Example: 1
     * @bodyParam city_id integer Example: 4
     *
     * @response 200 scenario="success" {"data": {"id": 1, "full_name": "Ahmed Al-Otaibi"}}
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profiles->updateProfile($request->user(), $request->validated());

        return response()->json(['data' => new ProfileResource($user)]);
    }

    /**
     * Upload/replace avatar
     *
     * @group Core / Profile
     *
     * @authenticated
     *
     * @bodyParam avatar file required Image, max 2MB. Example: (binary)
     *
     * @response 200 scenario="success" {"data": {"avatar_url": "https://.../avatars/users/abc.jpg"}}
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $this->profiles->updateAvatar($request->user(), $request->file('avatar'));

        return response()->json(['data' => new ProfileResource($request->user()->refresh())]);
    }

    /**
     * Remove avatar
     *
     * @group Core / Profile
     *
     * @authenticated
     *
     * @response 204
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
     *
     * @group Core / Profile
     *
     * @authenticated
     *
     * @bodyParam language string required "ar" or "en". Example: ar
     *
     * @response 200 scenario="success" {"data": {"preferred_language": "ar"}}
     */
    public function updateLanguage(UpdateLanguageRequest $request): JsonResponse
    {
        $this->profiles->updateLanguage($request->user(), $request->string('language')->value());

        return response()->json(['data' => new ProfileResource($request->user()->refresh())]);
    }

    /**
     * Set currency preference
     *
     * @group Core / Profile
     *
     * @authenticated
     *
     * @bodyParam currency_id integer required Example: 1
     *
     * @response 200 scenario="success" {"data": {"preferred_currency": {"id": 1, "code": "SAR"}}}
     */
    public function updateCurrency(UpdateCurrencyRequest $request): JsonResponse
    {
        $this->profiles->updateCurrency($request->user(), $request->integer('currency_id'));

        return response()->json(['data' => new ProfileResource($request->user()->refresh())]);
    }
}
