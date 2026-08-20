@extends('admin.layouts.app')

@section('title', __('admin.users.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.users.index') }}" class="d-flex gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.users.search_placeholder') }}"
                    class="form-control" style="max-width: 320px;">
                <button type="submit" class="btn btn-secondary">{{ __('admin.common.search') }}</button>
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
                            <th>{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email ?? __('admin.common.none') }}</td>
                                <td>{{ $user->mobile ?? __('admin.common.none') }}</td>
                                <td>
                                    <span class="badge {{ $user->status->value === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $user->status->value }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $user->hasVerifiedIdentity() ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ $user->hasVerifiedIdentity() ? __('admin.common.yes') : __('admin.common.no') }}
                                    </span>
                                </td>
                                <td>{{ $user->tokens_count }}</td>
                                <td>
                                    @can('users.view')
                                        <a href="{{ route('admin.users.show', $user) }}" class="text-muted">{{ __('admin.users.view') }}</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">{{ $users->links() }}</div>
        </div>
    </div>
@endsection
