@extends('admin.layouts.app')

@section('title', __('admin.providers.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.providers.index') }}" class="d-flex gap-2">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-body"><i class="ri-search-line"></i></span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.providers.search_placeholder') }}" class="form-control">
                </div>
                <select name="status" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
                    <option value="">{{ __('admin.providers.status_filter_all') }}</option>
                    <option value="pending_review" @selected($status === 'pending_review')>{{ __('admin.providers.status_pending_review') }}</option>
                    <option value="published" @selected($status === 'published')>{{ __('admin.providers.status_published') }}</option>
                    <option value="suspended" @selected($status === 'suspended')>{{ __('admin.providers.status_suspended') }}</option>
                </select>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('admin.providers.company_name') }}</th>
                            <th>{{ __('admin.providers.categories') }}</th>
                            <th>{{ __('admin.providers.status') }}</th>
                            <th>{{ __('admin.providers.verification_status') }}</th>
                            <th>{{ __('admin.providers.submitted_at') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($providers as $provider)
                            <tr>
                                <td>{{ $provider->company_name }}</td>
                                <td>{{ $provider->categories->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en')->join(', ') ?: __('admin.common.none') }}</td>
                                <td>
                                    <span class="badge {{ match ($provider->status->value) {
                                        'published' => 'bg-success-subtle text-success',
                                        'pending_review' => 'bg-warning-subtle text-warning',
                                        default => 'bg-danger-subtle text-danger',
                                    } }}">
                                        {{ $provider->status->value }}
                                    </span>
                                </td>
                                <td>{{ $provider->verification?->status->value ?? __('admin.common.none') }}</td>
                                <td>{{ $provider->verification?->submitted_at?->diffForHumans() ?? __('admin.common.none') }}</td>
                                <td class="text-end">
                                    @can('providers.view')
                                        <a href="{{ route('admin.providers.show', $provider) }}"
                                            class="btn btn-sm btn-soft-primary rounded-circle" style="width: 32px; height: 32px;"
                                            data-bs-toggle="tooltip" title="{{ __('admin.providers.view') }}">
                                            <i class="ri-eye-line align-middle"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ __('admin.common.none') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">{{ $providers->links() }}</div>
        </div>
    </div>
@endsection
