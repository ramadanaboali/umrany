<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

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

final class ProviderController extends Controller
{
    public function __construct(
        private readonly ProviderService $providers,
    ) {}

    /**
     * View my provider profile
     *
     * @group Core / Provider
     *
     * @authenticated
     *
     * @response 200 scenario="success" {"data": {"id": 1, "company_name": "Al-Otaibi Contracting"}}
     * @response 404 scenario="no provider profile" {"message": "No query results for model."}
     */
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
     *
     * @group Core / Provider
     *
     * @authenticated
     *
     * @bodyParam company_name string required Example: Al-Otaibi Contracting
     * @bodyParam country_id integer Example: 1
     * @bodyParam city_id integer Example: 4
     * @bodyParam commercial_registration_number string Example: 1010123456
     *
     * @response 201 scenario="success" {"data": {"id": 1, "company_name": "Al-Otaibi Contracting", "status": "pending_review"}}
     * @response 422 scenario="already has a provider profile" {"message": "The given data was invalid.", "errors": {"company_name": ["This account already has a provider profile."]}}
     */
    public function store(ActivateProviderRequest $request): JsonResponse
    {
        $provider = $this->providers->activate($request->user(), $request->validated());

        return response()->json(['data' => new ProviderResource($provider)], 201);
    }

    /**
     * Update provider profile
     *
     * @group Core / Provider
     *
     * @authenticated
     *
     * @response 200 scenario="success" {"data": {"id": 1, "company_name": "Al-Otaibi Contracting"}}
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
     *
     * @group Core / Provider
     *
     * @authenticated
     *
     * @bodyParam documents array required Example: [{"type": "commercial_registration", "file": "(binary)"}]
     *
     * @response 200 scenario="success" {"data": {"status": "pending"}}
     * @response 422 scenario="not resubmittable right now" {"message": "The given data was invalid.", "errors": {"documents": ["Verification cannot be resubmitted while its status is \"pending\"."]}}
     */
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
     *
     * @group Core / Provider
     *
     * @authenticated
     *
     * @bodyParam logo file required Image, max 2MB. Example: (binary)
     *
     * @response 200 scenario="success" {"data": {"id": 1, "logo_url": "https://.../providers/1/logo/abc.jpg"}}
     */
    public function updateLogo(UpdateProviderLogoRequest $request): JsonResponse
    {
        $this->providers->updateLogo($request->user()->provider, $request->file('logo'));

        return response()->json(['data' => new ProviderResource($request->user()->provider->refresh())]);
    }

    /**
     * Remove company logo
     *
     * @group Core / Provider
     *
     * @authenticated
     *
     * @response 204
     */
    public function destroyLogo(Request $request): JsonResponse
    {
        $this->providers->removeLogo($request->user()->provider);

        return response()->json(null, 204);
    }

    /**
     * Upload/replace cover image
     *
     * @group Core / Provider
     *
     * @authenticated
     *
     * @bodyParam cover file required Image, max 4MB. Example: (binary)
     *
     * @response 200 scenario="success" {"data": {"id": 1, "cover_url": "https://.../providers/1/cover/abc.jpg"}}
     */
    public function updateCover(UpdateProviderCoverRequest $request): JsonResponse
    {
        $this->providers->updateCover($request->user()->provider, $request->file('cover'));

        return response()->json(['data' => new ProviderResource($request->user()->provider->refresh())]);
    }

    /**
     * Remove cover image
     *
     * @group Core / Provider
     *
     * @authenticated
     *
     * @response 204
     */
    public function destroyCover(Request $request): JsonResponse
    {
        $this->providers->removeCover($request->user()->provider);

        return response()->json(null, 204);
    }
}
