<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\RejectProviderRequest;
use App\Http\Requests\Admin\SuspendProviderRequest;
use App\Http\Requests\Admin\UpdateProviderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Models\City;
use Modules\Core\Models\Country;
use Modules\Core\Models\GovernmentVerificationRequest;
use Modules\Core\Models\Provider;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Services\Admin\ProviderManagementService;

/**
 * Admin verification review + profile lifecycle screen — the piece nothing before this change
 * could ever do: approve/reject a verification submission, or publish/suspend/reactivate a
 * provider profile. Same thin-controller shape as UserController. See
 * docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md.
 */
final class ProviderController extends Controller
{
    public function __construct(
        private readonly ProviderManagementService $providers,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->filled('status') ? ProviderStatus::tryFrom($request->string('status')->value()) : null;

        return view('admin.providers.index', [
            'providers' => $this->providers->paginateForAdmin(
                search: $request->string('search')->value() ?: null,
                status: $status,
            ),
            'search' => $request->string('search')->value(),
            'status' => $request->string('status')->value(),
        ]);
    }

    public function show(Provider $provider): View
    {
        $provider->load(['verification.reviewedBy', 'categories', 'subcategories', 'documents', 'portfolios', 'certificates', 'user', 'country', 'city', 'governmentVerificationRequests' => fn ($query) => $query->latest('requested_at')]);

        return view('admin.providers.show', [
            'provider' => $provider,
            'countries' => Country::query()->where('is_active', true)->orderBy('name_en')->get(),
            'cities' => $provider->country_id
                ? City::query()->where('country_id', $provider->country_id)->where('is_active', true)->orderBy('name_en')->get()
                : collect(),
            'categories' => ProviderCategory::query()->orderBy('display_order')->get(),
        ]);
    }

    public function update(UpdateProviderRequest $request, Provider $provider): RedirectResponse
    {
        $this->providers->updateByAdmin($provider, $request->validated());

        return redirect()->route('admin.providers.show', $provider)->with('status', __('admin.flash.provider_updated'));
    }

    public function approve(Provider $provider): RedirectResponse
    {
        $this->providers->approve($provider, Auth::guard('admin')->user());

        return redirect()->route('admin.providers.show', $provider)->with('status', __('admin.flash.provider_approved'));
    }

    public function reject(RejectProviderRequest $request, Provider $provider): RedirectResponse
    {
        $this->providers->reject($provider, $request->string('reason')->value(), Auth::guard('admin')->user());

        return redirect()->route('admin.providers.show', $provider)->with('status', __('admin.flash.provider_rejected'));
    }

    public function suspend(SuspendProviderRequest $request, Provider $provider): RedirectResponse
    {
        $this->providers->suspend($provider, $request->string('reason')->value());

        return redirect()->route('admin.providers.show', $provider)->with('status', __('admin.flash.provider_suspended'));
    }

    public function reactivate(Provider $provider): RedirectResponse
    {
        $this->providers->reactivate($provider);

        return redirect()->route('admin.providers.show', $provider)->with('status', __('admin.flash.provider_reactivated'));
    }

    /**
     * FR-PROVIDER-004: applies a completed government verification's imported data to the
     * provider's own fields — see ProviderManagementService::applyGovernmentVerification() for
     * the "never overwrite already-set fields" rule.
     */
    public function applyGovernmentVerification(Provider $provider, GovernmentVerificationRequest $governmentVerificationRequest): RedirectResponse
    {
        $this->providers->applyGovernmentVerification($provider, $governmentVerificationRequest, Auth::guard('admin')->user());

        return redirect()->route('admin.providers.show', $provider)->with('status', __('admin.flash.provider_government_verification_applied'));
    }
}
