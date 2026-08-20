@extends('admin.layouts.app')

@section('title', __('admin.nav.dashboard'))

@section('content')
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <p class="mb-2">
                        {{ __('admin.dashboard.signed_in_as') }}
                        <strong>{{ Auth::guard('admin')->user()->name }}</strong>
                        @if (Auth::guard('admin')->user()->is_super_admin)
                            <span class="badge bg-success-subtle text-success">{{ __('admin.dashboard.super_admin_badge') }}</span>
                        @endif
                    </p>
                    <p class="mb-3">{{ trans_choice('admin.dashboard.admin_count', $adminCount, ['count' => $adminCount]) }}</p>
                    <p class="text-muted fs-13 mb-0">{{ __('admin.dashboard.aggregate_note') }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
