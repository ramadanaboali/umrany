@extends('admin.layouts.app')

@section('title', __('admin.roles.title'))

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h5 class="card-title mb-0">{{ __('admin.roles.title') }}</h5>
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="{{ route('admin.roles.index') }}">
                    <div class="input-group input-group-sm" style="max-width: 260px;">
                        <span class="input-group-text bg-body"><i class="ri-search-line"></i></span>
                        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.common.search') }}" class="form-control">
                    </div>
                </form>
                @can('roles.create')
                    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary btn-sm">{{ __('admin.roles.new') }}</a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted fs-13">{{ __('admin.roles.intro') }}</p>

            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('admin.roles.role') }}</th>
                            <th>{{ __('admin.roles.permissions') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
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
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        @can('roles.update')
                                            <a href="{{ route('admin.roles.edit', $role) }}"
                                                class="btn btn-sm btn-soft-primary rounded-circle" style="width: 32px; height: 32px;"
                                                data-bs-toggle="tooltip" title="{{ __('admin.common.edit') }}">
                                                <i class="ri-edit-line align-middle"></i>
                                            </a>
                                        @endcan
                                        @can('roles.delete')
                                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                                                onsubmit="return confirm('{{ __('admin.confirm.delete_role') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-soft-danger rounded-circle" style="width: 32px; height: 32px;"
                                                    data-bs-toggle="tooltip" title="{{ __('admin.common.delete') }}">
                                                    <i class="ri-delete-bin-line align-middle"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">{{ __('admin.common.none') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">{{ $roles->links() }}</div>
        </div>
    </div>
@endsection
