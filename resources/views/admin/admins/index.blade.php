@extends('admin.layouts.app')

@section('title', __('admin.admins.title'))

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">{{ __('admin.admins.title') }}</h5>
            @can('admins.create')
                <a href="{{ route('admin.admins.create') }}" class="btn btn-primary btn-sm">{{ __('admin.admins.new') }}</a>
            @endcan
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
                            <th>{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($admins as $admin)
                            <tr>
                                <td>{{ $admin->name }}</td>
                                <td>{{ $admin->email }}</td>
                                <td>{{ $admin->roles->pluck('name')->join(', ') ?: __('admin.common.none') }}</td>
                                <td>
                                    <span class="badge {{ $admin->status->value === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $admin->status->value }}
                                    </span>
                                </td>
                                <td>
                                    @can('admins.update')
                                        <a href="{{ route('admin.admins.edit', $admin) }}" class="text-muted">{{ __('admin.common.edit') }}</a>
                                    @endcan
                                    @can('admins.delete')
                                        <form method="POST" action="{{ route('admin.admins.destroy', $admin) }}" class="d-inline" onsubmit="return confirm('{{ __('admin.confirm.delete_admin') }}');">
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

            <div class="d-flex justify-content-end mt-3">{{ $admins->links() }}</div>
        </div>
    </div>
@endsection
