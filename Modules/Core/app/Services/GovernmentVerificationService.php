<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\GovernmentVerificationClient;
use Modules\Core\Enums\GovernmentVerificationStatus;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Models\GovernmentVerificationRequest;
use Modules\Core\Models\Provider;

/**
 * FR-PROVIDER-004. Uses the provider's own on-file commercial_registration_number — the spec's
 * "Provider enters Commercial Registration Number" already happened at profile activation, this
 * doesn't re-collect it. See docs/decisions/0031-government-verification-mock-integration.md.
 */
final class GovernmentVerificationService
{
    public function __construct(
        private readonly GovernmentVerificationClient $client,
        private readonly NotificationDispatchService $notifications,
    ) {}

    public function check(Provider $provider): GovernmentVerificationRequest
    {
        if ($provider->commercial_registration_number === null) {
            // Defensive only — RequestGovernmentVerificationRequest already blocks this at the
            // API boundary. The end-user API stays English-only (docs/decisions/0022-admin-
            // dashboard-en-ar-localization.md), so this is a plain literal, not __()'d.
            throw ValidationException::withMessages([
                'commercial_registration_number' => ['A commercial registration number is required before requesting government verification.'],
            ]);
        }

        $result = $this->client->check($provider->commercial_registration_number);

        $request = DB::transaction(function () use ($provider, $result) {
            return GovernmentVerificationRequest::forceCreate([
                'provider_id' => $provider->id,
                'commercial_registration_number' => $provider->commercial_registration_number,
                'status' => $result->succeeded ? GovernmentVerificationStatus::Completed : GovernmentVerificationStatus::Failed,
                'source' => 'mock',
                'imported_data' => $result->data?->toArray(),
                'failure_reason' => $result->failureReason,
                'requested_at' => now(),
                'completed_at' => now(),
            ]);
        });

        $this->notifications->send(
            $provider->user,
            NotificationEvent::GovernmentVerificationCompleted,
            ['succeeded' => $result->succeeded, 'failure_reason' => $result->failureReason],
            null,
            app()->getLocale(),
        );

        return $request;
    }
}
