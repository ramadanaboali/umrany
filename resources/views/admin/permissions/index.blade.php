@extends('admin.layouts.app')

@section('title', 'Permissions')

@section('content')
    <p style="color:#6b7280; font-size:.85rem;">
        Read-only — the permission catalog is code-defined and grows as new admin screens ship
        (root <code>CLAUDE.md</code> Rule 0). To grant a permission to admins, assign it to a
        <a href="{{ route('admin.roles.index') }}">role</a> instead.
    </p>

    @foreach ($permissionGroups as $group => $permissions)
        <h3 style="margin-top:1.5rem; text-transform:capitalize;">{{ $group }}</h3>
        <table>
            <thead>
                <tr>
                    <th>Permission</th>
                    <th>Granted via roles</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($permissions as $permission)
                    <tr>
                        <td>{{ $permission->name }}</td>
                        <td>{{ $permission->roles->pluck('name')->join(', ') ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@endsection
