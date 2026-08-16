@extends('admin.layouts.app')

@section('title', 'Admins')

@section('content')
    @can('admins.create')
        <p><a href="{{ route('admin.admins.create') }}" class="btn">+ New admin</a></p>
    @endcan

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
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
                    <td>
                        @can('admins.update')
                            <a href="{{ route('admin.admins.edit', $admin) }}">Edit</a>
                        @endcan
                        @can('admins.delete')
                            <form method="POST" action="{{ route('admin.admins.destroy', $admin) }}" style="display:inline;" onsubmit="return confirm('Delete this admin?');">
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

    <div style="margin-top:1rem;">{{ $admins->links() }}</div>
@endsection
