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
                </tbody>
            </table>

            @can('users.update')
                @if ($activeSessionCount > 0)
                    <form method="POST" action="{{ route('admin.users.sessions.destroy', $user) }}" onsubmit="return confirm('{{ __('admin.confirm.revoke_sessions') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-soft-danger">{{ __('admin.users.revoke_all_sessions') }}</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@endsection
