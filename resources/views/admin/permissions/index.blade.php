@extends('admin.layouts.app')

@section('title', __('admin.permissions.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('admin.permissions.index') }}">
                <div class="input-group input-group-sm" style="max-width: 260px;">
                    <span class="input-group-text bg-body"><i class="ri-search-line"></i></span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.common.search') }}" class="form-control">
                </div>
            </form>
        </div>
        <div class="card-body">
            <p class="text-muted fs-13">
                {!! __('admin.permissions.intro', ['role_link' => '<a href="'.route('admin.roles.index').'">'.__('admin.permissions.role_link').'</a>']) !!}
            </p>

            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('admin.permissions.resource') }}</th>
                            @foreach ($actions as $action)
                                <th class="text-center">{{ __('admin.permissions.actions.'.$action) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matrix as $resource => $permissions)
                            <tr>
                                <td>{{ __('admin.permissions.resources.'.$resource) }}</td>
                                @foreach ($actions as $action)
                                    <td class="text-center">
                                        @if ($permissions->has($action))
                                            <i class="ri-check-line text-success fs-16" title="{{ $permissions[$action]->name }}"></i>
                                        @else
                                            <span class="text-muted opacity-50">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($actions) + 1 }}" class="text-center text-muted py-4">{{ __('admin.common.none') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">{{ $matrix->links() }}</div>
        </div>
    </div>
@endsection
