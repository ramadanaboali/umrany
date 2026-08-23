@extends('admin.layouts.app')

@section('title', __('admin.users.details_title'))

@section('content')
    <div class="card">
        <div class="card-body">
            <table class="table table-borderless mb-3">
                <tbody>
                    <tr><th class="text-muted" style="width: 220px;">{{ __('admin.users.name') }}</th><td>{{ $user->name }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.users.email') }}</th><td>{{ $user->email ?? __('admin.common.none') }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.users.mobile') }}</th><td>{{ $user->mobile ?? __('admin.common.none') }}</td></tr>
                    <tr>
                        <th class="text-muted">{{ __('admin.users.status') }}</th>
                        <td>
                            <span class="badge {{ match ($user->status->value) {
                                'active' => 'bg-success-subtle text-success',
                                'pending_verification' => 'bg-warning-subtle text-warning',
                                default => 'bg-danger-subtle text-danger',
                            } }}">
                                {{ $user->status->value }}
                            </span>
                        </td>
                    </tr>
                    <tr><th class="text-muted">{{ __('admin.users.verified') }}</th><td>{{ $user->hasVerifiedIdentity() ? __('admin.common.yes') : __('admin.common.no') }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.users.account_types') }}</th><td>{{ collect($user->account_types ?? [])->join(', ') ?: __('admin.common.none') }}</td></tr>
                    <tr><th class="text-muted">{{ __('admin.users.last_login') }}</th><td>{{ $user->last_login_at?->diffForHumans() ?? __('admin.users.never') }} ({{ $user->last_login_ip ?? __('admin.common.none') }})</td></tr>
                    <tr><th class="text-muted">{{ __('admin.users.active_sessions') }}</th><td>{{ $activeSessionCount }}</td></tr>
                    @if ($user->status->value === 'suspended')
                        <tr><th class="text-muted">{{ __('admin.users.suspension_reason') }}</th><td>{{ $user->suspension_reason }}</td></tr>
                        <tr><th class="text-muted">{{ __('admin.users.suspended_at') }}</th><td>{{ $user->suspended_at?->diffForHumans() }}</td></tr>
                        <tr><th class="text-muted">{{ __('admin.users.suspended_by') }}</th><td>{{ $user->suspendedBy?->name ?? __('admin.common.none') }}</td></tr>
                    @endif
                </tbody>
            </table>

            <div class="d-flex gap-2">
                @can('users.update')
                    <button type="button" class="btn btn-soft-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="ri-pencil-line align-middle me-1"></i>{{ __('admin.users.edit_profile') }}
                    </button>

                    @if ($activeSessionCount > 0)
                        <form method="POST" action="{{ route('admin.users.sessions.destroy', $user) }}" onsubmit="return confirm('{{ __('admin.confirm.revoke_sessions') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-soft-danger"><i class="ri-close-circle-line align-middle me-1"></i>{{ __('admin.users.revoke_all_sessions') }}</button>
                        </form>
                    @endif

                    @if ($user->status->value === 'suspended')
                        <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" onsubmit="return confirm('{{ __('admin.confirm.reactivate_user') }}');">
                            @csrf
                            <button type="submit" class="btn btn-soft-success"><i class="ri-play-circle-line align-middle me-1"></i>{{ __('admin.users.reactivate') }}</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-soft-warning" data-bs-toggle="modal" data-bs-target="#suspendUserModal">
                            <i class="ri-forbid-line align-middle me-1"></i>{{ __('admin.users.suspend') }}
                        </button>
                    @endif
                @endcan

                @can('users.delete')
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('{{ __('admin.confirm.delete_user') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-soft-danger"><i class="ri-delete-bin-line align-middle me-1"></i>{{ __('admin.users.delete') }}</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    @can('users.update')
        <div class="modal fade" id="suspendUserModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('admin.users.suspend_modal_title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="suspend-reason" class="form-label">{{ __('admin.users.suspend_reason_label') }}</label>
                        <textarea name="reason" id="suspend-reason" class="form-control" rows="3" required minlength="5" maxlength="1000"></textarea>
                        @error('reason')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.users.cancel') }}</button>
                        <button type="submit" class="btn btn-warning">{{ __('admin.users.suspend_confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('admin.users.update-profile', $user) }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('admin.users.edit_profile_modal_title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit-full-name" class="form-label">{{ __('admin.users.full_name') }}</label>
                                <input type="text" name="full_name" id="edit-full-name" class="form-control" maxlength="255" value="{{ old('full_name', $user->profile?->full_name) }}">
                                @error('full_name')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="edit-email" class="form-label">{{ __('admin.users.email') }}</label>
                                <input type="email" name="email" id="edit-email" class="form-control" maxlength="255" value="{{ old('email', $user->email) }}">
                                @error('email')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="edit-mobile" class="form-label">{{ __('admin.users.mobile') }}</label>
                                <input type="text" name="mobile" id="edit-mobile" class="form-control" maxlength="20" value="{{ old('mobile', $user->mobile) }}">
                                @error('mobile')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="edit-address" class="form-label">{{ __('admin.users.address') }}</label>
                                <input type="text" name="address" id="edit-address" class="form-control" maxlength="255" value="{{ old('address', $user->profile?->address) }}">
                                @error('address')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="edit-country" class="form-label">{{ __('admin.users.country') }}</label>
                                <select name="country_id" id="edit-country" class="form-select" data-cities-url="{{ url('/api/v1/core/countries') }}">
                                    <option value="">{{ __('admin.users.select_country') }}</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}" @selected((int) old('country_id', $user->profile?->country_id) === $country->id)>
                                            {{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('country_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="edit-city" class="form-label">{{ __('admin.users.city') }}</label>
                                <select name="city_id" id="edit-city" class="form-select">
                                    <option value="">{{ __('admin.users.select_city') }}</option>
                                    @foreach ($cities as $city)
                                        <option value="{{ $city->id }}" @selected((int) old('city_id', $user->profile?->city_id) === $city->id)>
                                            {{ app()->getLocale() === 'ar' ? $city->name_ar : $city->name_en }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('city_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.users.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('admin.common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @push('scripts')
        <script>
            // Re-populates #edit-city select from the public master-data endpoint whenever the
            // country changes — the initial option list (server-rendered from $cities) already
            // matches the user's current country, so this only runs on an actual change.
            (function () {
                const countrySelect = document.getElementById('edit-country');
                const citySelect = document.getElementById('edit-city');

                if (!countrySelect || !citySelect) {
                    return;
                }

                const isArabic = document.documentElement.getAttribute('lang')?.startsWith('ar');

                countrySelect.addEventListener('change', function () {
                    const countryId = this.value;
                    citySelect.innerHTML = '<option value="">{{ __('admin.users.select_city') }}</option>';

                    if (!countryId) {
                        return;
                    }

                    fetch(`${this.dataset.citiesUrl}/${countryId}/cities`)
                        .then((response) => response.json())
                        .then((body) => {
                            body.data.forEach((city) => {
                                const option = document.createElement('option');
                                option.value = city.id;
                                option.textContent = isArabic ? city.name_ar : city.name_en;
                                citySelect.appendChild(option);
                            });
                        });
                });
            })();
        </script>
    @endpush
@endsection
