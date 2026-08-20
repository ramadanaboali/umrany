<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Requests\Provider\ActivateProviderRequest;
use Modules\Core\Http\Requests\Provider\SubmitProviderVerificationRequest;
use Modules\Core\Http\Requests\Provider\UpdateProviderCoverRequest;
use Modules\Core\Http\Requests\Provider\UpdateProviderLogoRequest;
use Modules\Core\Http\Requests\Provider\UpdateProviderRequest;
use Modules\Core\Http\Resources\ProviderResource;
use Modules\Core\Services\ProviderService;

#[Group('Core / Provider', weight: 3)]
final class ProviderController extends Controller
{
    public function __construct(
        private readonly ProviderService $providers,
    ) {}

    /**
     * View my provider profile
     */
    #[Response(404, description: 'This account has not activated a provider profile.')]
    public function show(Request $request): JsonResponse
    {
        $provider = $request->user()->provider()->with('verification')->firstOrFail();

        return response()->json(['data' => new ProviderResource($provider)]);
    }

    /**
     * Activate provider profile
     *
     * FR-PROVIDER-001: any verified registered user may activate exactly one provider profile.
     * Publicly visible only after verification is approved and status is set to published by an
     * admin — see Modules\Core\Models\Provider::isPubliclyVisible().
     */
    #[Response(422, description: 'This account already has a provider profile.')]
    public function store(ActivateProviderRequest $request): JsonResponse
    {
        $provider = $this->providers->activate($request->user(), $request->validated());

        return response()->json(['data' => new ProviderResource($provider)], 201);
    }

    /**
     * Update provider profile
     */
    public function update(UpdateProviderRequest $request): JsonResponse
    {
        $provider = $this->providers->update($request->user()->provider, $request->validated());

        return response()->json(['data' => new ProviderResource($provider)]);
    }

    /**
     * Submit verification documents
     *
     * FR-PROVIDER-005: providers upload documents for manual admin review. Only allowed while
     * the current verification status is not_submitted, rejected, or expired — see
     * Modules\Core\Enums\ProviderVerificationStatus::canSubmit().
     */
    #[Response(422, description: 'Verification cannot be resubmitted while a review is already in progress or once approved.')]
    public function submitVerification(SubmitProviderVerificationRequest $request): JsonResponse
    {
        $verification = $this->providers->submitVerification($request->user()->provider, $request->validated('documents'));

        return response()->json(['data' => [
            'status' => $verification->status->value,
            'submitted_at' => $verification->submitted_at,
        ]]);
    }

    /**
     * Upload/replace company logo
     */
    public function updateLogo(UpdateProviderLogoRequest $request): JsonResponse
    {
        $this->providers->updateLogo($request->user()->provider, $request->file('logo'));

        return response()->json(['data' => new ProviderResource($request->user()->provider->refresh())]);
    }

    /**
     * Remove company logo
     */
    public function destroyLogo(Request $request): JsonResponse
    {
        $this->providers->removeLogo($request->user()->provider);

        return response()->json(null, 204);
    }

    /**
     * Upload/replace cover image
     */
    public function updateCover(UpdateProviderCoverRequest $request): JsonResponse
    {
        $this->providers->updateCover($request->user()->provider, $request->file('cover'));

        return response()->json(['data' => new ProviderResource($request->user()->provider->refresh())]);
    }

    /**
     * Remove cover image
     */
    public function destroyCover(Request $request): JsonResponse
    {
        $this->providers->removeCover($request->user()->provider);

        return response()->json(null, 204);
    }
}
