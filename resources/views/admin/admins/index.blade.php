@extends('admin.layouts.app')

@section('title', __('admin.admins.title'))

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h5 class="card-title mb-0">{{ __('admin.admins.title') }}</h5>
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="{{ route('admin.admins.index') }}" class="d-flex gap-2">
                    <div class="input-group input-group-sm" style="max-width: 260px;">
                        <span class="input-group-text bg-body"><i class="ri-search-line"></i></span>
                        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin.common.search') }}" class="form-control">
                    </div>
                </form>
                @can('admins.create')
                    <a href="{{ route('admin.admins.create') }}" class="btn btn-primary btn-sm">{{ __('admin.admins.new') }}</a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-nowrap align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('admin.admins.name') }}</th>
                            <th>{{ __('admin.admins.email') }}</th>
                            <th>{{ __('admin.admins.roles') }}</th>
                            <th>{{ __('admin.admins.status') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($admins as $admin)
                            <tr>
                                <td>{{ $admin->name }}</td>
                                <td>{{ $admin->email }}</td>
                                <td>{{ $admin->roles->pluck('name')->join(', ') ?: __('admin.common.none') }}</td>
                                <td>
                                    <span class="badge {{ $admin->status->value === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $admin->status->value }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        @can('admins.update')
                                            <a href="{{ route('admin.admins.edit', $admin) }}"
                                                class="btn btn-sm btn-soft-primary rounded-circle" style="width: 32px; height: 32px;"
                                                data-bs-toggle="tooltip" title="{{ __('admin.common.edit') }}">
                                                <i class="ri-edit-line align-middle"></i>
                                            </a>
                                        @endcan
                                        @can('admins.delete')
                                            <form method="POST" action="{{ route('admin.admins.destroy', $admin) }}"
                                                onsubmit="return confirm('{{ __('admin.confirm.delete_admin') }}');">
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
                                <td colspan="5" class="text-center text-muted py-4">{{ __('admin.common.none') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">{{ $admins->links() }}</div>
        </div>
    </div>
@endsection
