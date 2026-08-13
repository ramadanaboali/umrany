@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="card">
        <p>Signed in as <strong>{{ Auth::guard('admin')->user()->name }}</strong>
            @if (Auth::guard('admin')->user()->is_super_admin)
                <span class="badge badge-active">Super Admin</span>
            @endif
        </p>
        <p>{{ $adminCount }} admin account(s) on this platform.</p>
        <p style="color:#6b7280; font-size:.85rem;">
            Cross-product operational dashboards (users, providers, subscriptions, revenue) land
            once those modules have real data to aggregate — see docs/business/roadmap.md Phase 7.
        </p>
    </div>
@endsection
