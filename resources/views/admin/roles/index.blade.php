@extends('admin.layouts.app')

@section('title', __('admin.roles.title'))

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">{{ __('admin.roles.title') }}</h5>
            @can('roles.create')
                <a href="{{ route('admin.roles.create') }}" class="btn btn-primary btn-sm">{{ __('admin.roles.new') }}</a>
            @endcan
        </div>
        <div class="card-body">
            <p class="text-muted fs-13">{{ __('admin.roles.intro') }}</p>

            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('admin.roles.role') }}</th>
                            <th>{{ __('admin.roles.permissions') }}</th>
                            <th>{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>{{ $role->name }}</td>
                                <td>
                                    @php $shownPermissions = $role->permissions->take(-5); @endphp
                                    @forelse ($shownPermissions as $permission)
                                        <span class="badge bg-light text-body me-1">{{ $permission->name }}</span>
                                    @empty
                                        {{ __('admin.common.none') }}
                                    @endforelse
                                    @if ($role->permissions->count() > 5)
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            {{ trans_choice('admin.roles.more_permissions', $role->permissions->count() - 5, ['count' => $role->permissions->count() - 5]) }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @can('roles.update')
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="text-muted">{{ __('admin.common.edit') }}</a>
                                    @endcan
                                    @can('roles.delete')
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" onsubmit="return confirm('{{ __('admin.confirm.delete_role') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-soft-danger btn-sm">{{ __('admin.common.delete') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
