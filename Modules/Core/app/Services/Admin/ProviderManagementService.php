<?php

declare(strict_types=1);

namespace Modules\Core\Services\Admin;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\GovernmentVerificationStatus;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Models\Admin;
use Modules\Core\Models\GovernmentVerificationRequest;
use Modules\Core\Models\Provider;
use Modules\Core\Services\NotificationDispatchService;

/**
 * Admin-side provider verification review and profile lifecycle — the piece nothing before this
 * change could ever do: approve/reject a verification submission, or publish/suspend/reactivate a
 * provider profile. Same directory/naming convention as UserManagementService/RoleManagementService/
 * AdminManagementService. See docs/decisions/0030-provider-taxonomy-portfolio-and-admin-review.md.
 */
final class ProviderManagementService
{
    public function __construct(
        private readonly NotificationDispatchService $notifications,
    ) {}

    public function paginateForAdmin(int $perPage = 20, ?string $search = null, ?ProviderStatus $status = null): LengthAwarePaginator
    {
        return Provider::query()
            ->with(['verification', 'categories', 'user'])
            ->when($search !== null, fn ($query) => $query->where('company_name', 'like', '%'.$search.'%'))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Approving a verification is, per the spec's workflow, the single action that also publishes
     * the provider profile — not two separate admin steps.
     */
    public function approve(Provider $provider, Admin $actor): void
    {
        DB::transaction(function () use ($provider, $actor) {
            $provider->verification->forceFill([
                'status' => ProviderVerificationStatus::Approved,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $provider->forceFill(['status' => ProviderStatus::Published])->save();
        });

        $this->notifications->send($provider->user, NotificationEvent::ProviderVerificationApproved);
        $this->notifications->send($provider->user, NotificationEvent::ProviderProfilePublished);
    }

    /**
     * Provider profile status is left unchanged on rejection — rejection is purely a verification
     * outcome, not a profile-visibility one (a provider can be re-reviewed after fixing documents
     * without ever having been published).
     */
    public function reject(Provider $provider, string $reason, Admin $actor): void
    {
        $provider->verification->forceFill([
            'status' => ProviderVerificationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        $this->notifications->send($provider->user, NotificationEvent::ProviderVerificationRejected, ['reason' => $reason]);
    }

    /**
     * Independent of verification state — a Published, previously-Approved provider can still be
     * suspended for a policy violation without touching its verification row at all.
     */
    public function suspend(Provider $provider, string $reason): void
    {
        $provider->forceFill(['status' => ProviderStatus::Suspended])->save();

        $this->notifications->send($provider->user, NotificationEvent::ProviderProfileSuspended, ['reason' => $reason]);
    }

    public function reactivate(Provider $provider): void
    {
        $provider->forceFill(['status' => ProviderStatus::Published])->save();

        $this->notifications->send($provider->user, NotificationEvent::ProviderProfilePublished);
    }

    /**
     * Admin-side company-info edit — same fields the provider's own PUT /providers/me exposes,
     * scoped to the route-bound provider rather than the acting admin's own profile.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateByAdmin(Provider $provider, array $data): void
    {
        $provider->update($data);
    }

    /**
     * FR-PROVIDER-004: applies a completed government verification's imported data to the
     * provider's own fields — never overwrites a field the provider has already filled in (the
     * "government data must never silently overwrite user-entered data" gotcha, Modules/Core/
     * CLAUDE.md), so a provider whose company_name/address are already set keeps them exactly as
     * entered. A request can only ever be applied once and only once it Completed successfully.
     */
    public function applyGovernmentVerification(Provider $provider, GovernmentVerificationRequest $request, Admin $actor): void
    {
        if ($request->provider_id !== $provider->id) {
            throw (new ModelNotFoundException)->setModel(GovernmentVerificationRequest::class);
        }

        if ($request->status !== GovernmentVerificationStatus::Completed || $request->isApplied()) {
            return;
        }

        // company_name is a required (non-nullable) field, already set at activation — it never
        // has anything to merge into. address/year_established are the two Provider fields that
        // can genuinely still be null when a government check completes.
        $imported = $request->imported_data ?? [];

        $updates = array_filter([
            'address' => $provider->address === null ? ($imported['registered_address'] ?? null) : null,
            'year_established' => $provider->year_established === null && ! empty($imported['establishment_date'])
                ? (int) substr((string) $imported['establishment_date'], 0, 4)
                : null,
        ], fn ($value) => $value !== null);

        DB::transaction(function () use ($provider, $request, $actor, $updates) {
            if ($updates !== []) {
                $provider->update($updates);
            }

            $request->forceFill(['applied_by_admin_id' => $actor->id, 'applied_at' => now()])->save();
        });
    }
}
