@extends('admin.layouts.app')

@section('title', __('admin.permissions.title'))

@section('content')
    <div class="card">
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
                                <th class="text-capitalize">{{ $action }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix as $resource => $permissions)
                            <tr>
                                <td class="text-capitalize">{{ $resource }}</td>
                                @foreach ($actions as $action)
                                    <td>
                                        @if ($permissions->has($action))
                                            <span title="{{ $permissions[$action]->name }}">
                                                {{ $permissions[$action]->roles->pluck('name')->join(', ') ?: __('admin.common.none') }}
                                            </span>
                                        @else
                                            <span class="text-muted opacity-50">{{ __('admin.permissions.not_applicable') }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
