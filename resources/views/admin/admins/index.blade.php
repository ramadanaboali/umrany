@extends('admin.layouts.app')

@section('title', 'Admins')

@section('content')
    @can('admins.manage')
        <p><a href="{{ route('admin.admins.create') }}" class="btn">+ New admin</a></p>
    @endcan

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Super Admin</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($admins as $admin)
                <tr>
                    <td>{{ $admin->name }}</td>
                    <td>{{ $admin->email }}</td>
                    <td>{{ $admin->roles->pluck('name')->join(', ') ?: '—' }}</td>
                    <td><span class="badge badge-{{ $admin->status->value }}">{{ $admin->status->value }}</span></td>
                    <td>{{ $admin->is_super_admin ? 'Yes' : 'No' }}</td>
                    <td>
                        @can('admins.manage')
                            <a href="{{ route('admin.admins.edit', $admin) }}">Edit</a>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top:1rem;">{{ $admins->links() }}</div>
@endsection
