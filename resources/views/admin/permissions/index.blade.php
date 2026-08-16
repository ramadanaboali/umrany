@extends('admin.layouts.app')

@section('title', 'Permissions')

@section('content')
    <p style="color:#6b7280; font-size:.85rem;">
        The permission catalog is defined in code and expands automatically as new admin screens
        ship. To grant a permission to admins, assign it to a
        <a href="{{ route('admin.roles.index') }}">role</a> instead.
    </p>

    <table>
        <thead>
            <tr>
                <th>Resource</th>
                @foreach ($actions as $action)
                    <th style="text-transform:capitalize;">{{ $action }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($matrix as $resource => $permissions)
                <tr>
                    <td style="text-transform:capitalize;">{{ $resource }}</td>
                    @foreach ($actions as $action)
                        <td>
                            @if ($permissions->has($action))
                                <span title="{{ $permissions[$action]->name }}">
                                    {{ $permissions[$action]->roles->pluck('name')->join(', ') ?: '—' }}
                                </span>
                            @else
                                <span style="color:#d1d5db;">n/a</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
