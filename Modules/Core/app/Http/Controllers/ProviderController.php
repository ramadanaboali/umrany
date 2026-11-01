<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Requests\Provider\ActivateProviderRequest;
use Modules\Core\Http\Requests\Provider\RequestGovernmentVerificationRequest;
use Modules\Core\Http\Requests\Provider\StoreCertificateRequest;
use Modules\Core\Http\Requests\Provider\StorePortfolioRequest;
use Modules\Core\Http\Requests\Provider\SubmitProviderVerificationRequest;
use Modules\Core\Http\Requests\Provider\UpdateCertificateRequest;
use Modules\Core\Http\Requests\Provider\UpdatePortfolioRequest;
use Modules\Core\Http\Requests\Provider\UpdateProviderCoverRequest;
use Modules\Core\Http\Requests\Provider\UpdateProviderLogoRequest;
use Modules\Core\Http\Requests\Provider\UpdateProviderRequest;
use Modules\Core\Http\Resources\GovernmentVerificationRequestResource;
use Modules\Core\Http\Resources\ProviderCertificateResource;
use Modules\Core\Http\Resources\ProviderPortfolioResource;
use Modules\Core\Http\Resources\ProviderResource;
use Modules\Core\Models\Provider;
use Modules\Core\Models\ProviderCertificate;
use Modules\Core\Models\ProviderPortfolio;
use Modules\Core\Repositories\Contracts\ProviderRepositoryInterface;
use Modules\Core\Services\GovernmentVerificationService;
use Modules\Core\Services\ProviderService;

#[Group('Core / Provider', weight: 3)]
final class ProviderController extends Controller
{
    public function __construct(
        private readonly ProviderService $providers,
        private readonly ProviderRepositoryInterface $providerRepository,
        private readonly GovernmentVerificationService $governmentVerification,
    ) {}

    /**
     * List public providers
     *
     * FR-PROVIDER-001/FR-PROVIDER-005: only providers whose profile is Published AND whose
     * verification is Approved are ever listed — see Provider::isPubliclyVisible().
     */
    #[QueryParameter('filter[city_id]', description: 'Exact match.', type: 'integer')]
    #[QueryParameter('filter[country_id]', description: 'Exact match.', type: 'integer')]
    #[QueryParameter('filter[category]', description: 'Exact match against a category id.', type: 'integer')]
    #[QueryParameter('filter[subcategory]', description: 'Exact match against a subcategory id.', type: 'integer')]
    #[QueryParameter('filter[search]', description: 'Partial match against company_name.', type: 'string')]
    public function index(): AnonymousResourceCollection
    {
        return ProviderResource::collection($this->providerRepository->paginatePublic());
    }

    /**
     * View a public provider profile
     */
    #[Response(404, description: 'This provider is not publicly visible.')]
    public function showPublic(Provider $provider): JsonResponse
    {
        abort_unless($provider->isPubliclyVisible(), 404);

        return response()->json(['data' => new ProviderResource($provider->load(['verification', 'categories', 'subcategories', 'portfolios', 'certificates']))]);
    }

    /**
     * View my provider profile
     */
    #[Response(404, description: 'This account has not activated a provider profile.')]
    public function show(Request $request): JsonResponse
    {
        $provider = $request->user()->provider()->with(['verification', 'categories', 'subcategories', 'portfolios', 'certificates', 'latestGovernmentVerificationRequest'])->firstOrFail();

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
     * Request government verification
     *
     * FR-PROVIDER-004: checks the provider's own on-file commercial registration number against
     * the government registry. Mock integration — see Modules\Core\Services\
     * GovernmentVerificationService and docs/decisions/0031-government-verification-mock-
     * integration.md. A successful check never applies imported data to the profile
     * automatically — an administrator reviews and applies it separately.
     */
    #[Response(422, description: 'This provider has no commercial registration number on file.')]
    public function requestGovernmentVerification(RequestGovernmentVerificationRequest $request): JsonResponse
    {
        $verificationRequest = $this->governmentVerification->check($request->user()->provider);

        return response()->json(['data' => new GovernmentVerificationRequestResource($verificationRequest)]);
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

    /**
     * Add a portfolio item
     *
     * FR-PROVIDER-006.
     */
    public function storePortfolio(StorePortfolioRequest $request): JsonResponse
    {
        $portfolio = $this->providers->createPortfolio($request->user()->provider, $request->validated());

        return response()->json(['data' => new ProviderPortfolioResource($portfolio)], 201);
    }

    /**
     * Update a portfolio item
     */
    #[Response(404, description: 'This portfolio item does not belong to your provider profile.')]
    public function updatePortfolio(UpdatePortfolioRequest $request, ProviderPortfolio $portfolio): JsonResponse
    {
        $portfolio = $this->providers->updatePortfolio($request->user()->provider, $portfolio, $request->validated());

        return response()->json(['data' => new ProviderPortfolioResource($portfolio)]);
    }

    /**
     * Delete a portfolio item
     */
    #[Response(404, description: 'This portfolio item does not belong to your provider profile.')]
    public function destroyPortfolio(Request $request, ProviderPortfolio $portfolio): JsonResponse
    {
        $this->providers->deletePortfolio($request->user()->provider, $portfolio);

        return response()->json(null, 204);
    }

    /**
     * Add a certificate
     *
     * FR-PROVIDER-007.
     */
    public function storeCertificate(StoreCertificateRequest $request): JsonResponse
    {
        $certificate = $this->providers->createCertificate($request->user()->provider, $request->validated(), $request->file('file'));

        return response()->json(['data' => new ProviderCertificateResource($certificate)], 201);
    }

    /**
     * Update a certificate
     */
    #[Response(404, description: 'This certificate does not belong to your provider profile.')]
    public function updateCertificate(UpdateCertificateRequest $request, ProviderCertificate $certificate): JsonResponse
    {
        $certificate = $this->providers->updateCertificate($request->user()->provider, $certificate, $request->validated(), $request->file('file'));

        return response()->json(['data' => new ProviderCertificateResource($certificate)]);
    }

    /**
     * Delete a certificate
     */
    #[Response(404, description: 'This certificate does not belong to your provider profile.')]
    public function destroyCertificate(Request $request, ProviderCertificate $certificate): JsonResponse
    {
        $this->providers->deleteCertificate($request->user()->provider, $certificate);

        return response()->json(null, 204);
    }
}
