@extends('admin.layouts.app')

@section('title', __('admin.users.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.users.index') }}">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-body"><i class="ri-search-line"></i></span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.users.search_placeholder') }}" class="form-control">
                </div>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('admin.users.name') }}</th>
                            <th>{{ __('admin.users.email') }}</th>
                            <th>{{ __('admin.users.mobile') }}</th>
                            <th>{{ __('admin.users.status') }}</th>
                            <th>{{ __('admin.users.verified') }}</th>
                            <th>{{ __('admin.users.sessions') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email ?? __('admin.common.none') }}</td>
                                <td>{{ $user->mobile ?? __('admin.common.none') }}</td>
                                <td>
                                    <span class="badge {{ match ($user->status->value) {
                                        'active' => 'bg-success-subtle text-success',
                                        'pending_verification' => 'bg-warning-subtle text-warning',
                                        default => 'bg-danger-subtle text-danger',
                                    } }}">
                                        {{ $user->status->value }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $user->hasVerifiedIdentity() ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ $user->hasVerifiedIdentity() ? __('admin.common.yes') : __('admin.common.no') }}
                                    </span>
                                </td>
                                <td>{{ $user->tokens_count }}</td>
                                <td class="text-end">
                                    @can('users.view')
                                        <a href="{{ route('admin.users.show', $user) }}"
                                            class="btn btn-sm btn-soft-primary rounded-circle" style="width: 32px; height: 32px;"
                                            data-bs-toggle="tooltip" title="{{ __('admin.users.view') }}">
                                            <i class="ri-eye-line align-middle"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ __('admin.common.none') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">{{ $users->links() }}</div>
        </div>
    </div>
@endsection
