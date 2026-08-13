@extends('admin.layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <p style="color:#6b7280; font-size:.85rem;">
        Roles are admin-created permission containers, not fixed identities — create as many as
        your operation needs (Finance, Support, Sales, ... are examples, not requirements).
    </p>

    @can('roles.manage')
        <p><a href="{{ route('admin.roles.create') }}" class="btn">+ New role</a></p>
    @endcan

    <table>
        <thead>
            <tr>
                <th>Role</th>
                <th>Permissions</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($roles as $role)
                <tr>
                    <td>{{ $role->name }}</td>
                    <td>{{ $role->permissions->pluck('name')->join(', ') ?: '—' }}</td>
                    <td>
                        @can('roles.manage')
                            <a href="{{ route('admin.roles.edit', $role) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" style="display:inline;" onsubmit="return confirm('Delete this role?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary" style="padding:.2rem .5rem; font-size:.75rem;">Delete</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
