@extends('admin.layouts.app')

@section('title', __('admin.providers.details_title'))

@section('content')
    <div class="card">
        <div class="card-body">
            <table class="table table-borderless mb-3">
                <tbody>
                    <tr><th class="text-muted" style="width: 220px;">{{ __('admin.providers.company_name') }}</th><td>{{ $provider->company_name }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.providers.owner') }}</th><td>{{ $provider->user->name }} ({{ $provider->user->email ?? $provider->user->mobile }})</td></tr>
                    <tr>
                        <th class="text-muted">{{ __('admin.providers.categories') }}</th>
                        <td>
                            @forelse ($provider->categories as $category)
                                <span class="badge bg-primary-subtle text-primary me-1">{{ app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en }}</span>
                            @empty
                                {{ __('admin.common.none') }}
                            @endforelse
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">{{ __('admin.providers.subcategories') }}</th>
                        <td>
                            @forelse ($provider->subcategories as $subcategory)
                                <span class="badge bg-secondary-subtle text-secondary me-1">{{ app()->getLocale() === 'ar' ? $subcategory->name_ar : $subcategory->name_en }}</span>
                            @empty
                                {{ __('admin.common.none') }}
                            @endforelse
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">{{ __('admin.providers.status') }}</th>
                        <td>
                            <span class="badge {{ match ($provider->status->value) {
                                'published' => 'bg-success-subtle text-success',
                                'pending_review' => 'bg-warning-subtle text-warning',
                                default => 'bg-danger-subtle text-danger',
                            } }}">
                                {{ $provider->status->value }}
                            </span>
                        </td>
                    </tr>
                    <tr><th class="text-muted">{{ __('admin.providers.description') }}</th><td>{{ $provider->description ?? __('admin.common.none') }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.providers.country') }}</th><td>{{ $provider->country ? (app()->getLocale() === 'ar' ? $provider->country->name_ar : $provider->country->name_en) : __('admin.common.none') }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.providers.city') }}</th><td>{{ $provider->city ? (app()->getLocale() === 'ar' ? $provider->city->name_ar : $provider->city->name_en) : __('admin.common.none') }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.providers.commercial_registration_number') }}</th><td>{{ $provider->commercial_registration_number ?? __('admin.common.none') }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.providers.year_established') }}</th><td>{{ $provider->year_established ?? __('admin.common.none') }}</td></tr>

                    <tr><th class="text-muted">{{ __('admin.providers.verification_status') }}</th><td>{{ $provider->verification?->status->value ?? __('admin.common.none') }}</td></tr>
                    @if ($provider->verification?->status->value === 'rejected')
                        <tr><th class="text-muted">{{ __('admin.providers.rejection_reason') }}</th><td>{{ $provider->verification->rejection_reason }}</td></tr>
                    @endif
                    <tr><th class="text-muted">{{ __('admin.providers.reviewed_by') }}</th><td>{{ $provider->verification?->reviewedBy?->name ?? __('admin.common.none') }}</td></tr>

                    <tr>
                        <th class="text-muted">{{ __('admin.providers.documents') }}</th>
                        <td>
                            @forelse ($provider->documents as $document)
                                <div><a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}" target="_blank">{{ $document->original_name ?? $document->type->value }}</a></div>
                            @empty
                                {{ __('admin.common.none') }}
                            @endforelse
                        </td>
                    </tr>
                    <tr><th class="text-muted">{{ __('admin.providers.portfolio_count') }}</th><td>{{ $provider->portfolios->count() }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.providers.certificate_count') }}</th><td>{{ $provider->certificates->count() }}</td></tr>
                </tbody>
            </table>

            @can('providers.update')
                <div class="d-flex gap-2">
                    @if ($provider->verification?->status?->isReviewable())
                        <form method="POST" action="{{ route('admin.providers.approve', $provider) }}" onsubmit="return confirm('{{ __('admin.confirm.approve_provider') }}');">
                            @csrf
                            <button type="submit" class="btn btn-soft-success"><i class="ri-checkbox-circle-line align-middle me-1"></i>{{ __('admin.providers.approve') }}</button>
                        </form>

                        <button type="button" class="btn btn-soft-danger" data-bs-toggle="modal" data-bs-target="#rejectProviderModal">
                            <i class="ri-close-circle-line align-middle me-1"></i>{{ __('admin.providers.reject') }}
                        </button>
                    @endif

                    @if ($provider->status === \Modules\Core\Enums\ProviderStatus::Suspended)
                        <form method="POST" action="{{ route('admin.providers.reactivate', $provider) }}" onsubmit="return confirm('{{ __('admin.confirm.reactivate_provider') }}');">
                            @csrf
                            <button type="submit" class="btn btn-soft-success"><i class="ri-play-circle-line align-middle me-1"></i>{{ __('admin.providers.reactivate') }}</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-soft-warning" data-bs-toggle="modal" data-bs-target="#suspendProviderModal">
                            <i class="ri-forbid-line align-middle me-1"></i>{{ __('admin.providers.suspend') }}
                        </button>
                    @endif

                    <button type="button" class="btn btn-soft-primary" data-bs-toggle="modal" data-bs-target="#editProviderModal">
                        <i class="ri-pencil-line align-middle me-1"></i>{{ __('admin.providers.edit') }}
                    </button>
                </div>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('admin.providers.government_verification_title') }}</div>
        <div class="card-body">
            @forelse ($provider->governmentVerificationRequests as $governmentVerificationRequest)
                <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                    <div>
                        <span class="badge {{ match ($governmentVerificationRequest->status->value) {
                            'completed' => 'bg-success-subtle text-success',
                            'failed' => 'bg-danger-subtle text-danger',
                            default => 'bg-warning-subtle text-warning',
                        } }}">{{ $governmentVerificationRequest->status->value }}</span>
                        <span class="text-muted ms-2">{{ __('admin.providers.government_verification_requested_at') }}: {{ $governmentVerificationRequest->requested_at }}</span>

                        @if ($governmentVerificationRequest->status->value === 'failed')
                            <div class="small text-danger mt-1">{{ __('admin.providers.government_verification_failure_reason') }}: {{ $governmentVerificationRequest->failure_reason }}</div>
                        @elseif ($governmentVerificationRequest->imported_data)
                            <pre class="small text-muted mt-1 mb-0">{{ json_encode($governmentVerificationRequest->imported_data, JSON_PRETTY_PRINT) }}</pre>
                        @endif
                    </div>

                    @can('providers.update')
                        @if ($governmentVerificationRequest->status->value === 'completed')
                            @if ($governmentVerificationRequest->isApplied())
                                <span class="badge bg-secondary-subtle text-secondary">{{ __('admin.providers.government_verification_applied') }}</span>
                            @else
                                <form method="POST" action="{{ route('admin.providers.government-verification.apply', [$provider, $governmentVerificationRequest]) }}" onsubmit="return confirm('{{ __('admin.providers.government_verification_confirm_apply') }}');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-soft-primary">{{ __('admin.providers.government_verification_apply') }}</button>
                                </form>
                            @endif
                        @endif
                    @endcan
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('admin.providers.government_verification_none') }}</p>
            @endforelse
        </div>
    </div>

    @can('providers.update')
        <div class="modal fade" id="rejectProviderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.providers.reject', $provider) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('admin.providers.reject') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="reject-reason" class="form-label">{{ __('admin.providers.reject_reason_label') }}</label>
                        <textarea name="reason" id="reject-reason" class="form-control" rows="3" required minlength="5" maxlength="1000"></textarea>
                        @error('reason')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.providers.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('admin.providers.confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="suspendProviderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.providers.suspend', $provider) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('admin.providers.suspend') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="suspend-reason" class="form-label">{{ __('admin.providers.suspend_reason_label') }}</label>
                        <textarea name="reason" id="suspend-reason" class="form-control" rows="3" required minlength="5" maxlength="1000"></textarea>
                        @error('reason')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.providers.cancel') }}</button>
                        <button type="submit" class="btn btn-warning">{{ __('admin.providers.confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="editProviderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('admin.providers.update', $provider) }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('admin.providers.edit') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit-company-name" class="form-label">{{ __('admin.providers.company_name') }}</label>
                                <input type="text" name="company_name" id="edit-company-name" class="form-control" maxlength="255" value="{{ old('company_name', $provider->company_name) }}">
                                @error('company_name')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="edit-cr-number" class="form-label">{{ __('admin.providers.commercial_registration_number') }}</label>
                                <input type="text" name="commercial_registration_number" id="edit-cr-number" class="form-control" maxlength="64" value="{{ old('commercial_registration_number', $provider->commercial_registration_number) }}">
                                @error('commercial_registration_number')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label for="edit-description" class="form-label">{{ __('admin.providers.description') }}</label>
                                <textarea name="description" id="edit-description" class="form-control" rows="3">{{ old('description', $provider->description) }}</textarea>
                                @error('description')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.providers.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('admin.common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection
