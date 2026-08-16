@extends('admin.layouts.app')

@section('title', 'Edit admin')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.admins.update', $admin) }}">
            @csrf
            @method('PUT')

            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $admin->name) }}" required>

            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="{{ old('phone', $admin->phone) }}">

            <label for="email">Email (read-only)</label>
            <input type="text" id="email" value="{{ $admin->email }}" disabled>

            <label for="status">Status</label>
            <select id="status" name="status" {{ $admin->id === Auth::guard('admin')->id() ? 'disabled' : '' }}>
                @foreach (\Modules\Core\Enums\AdminStatus::cases() as $status)
                    <option value="{{ $status->value }}" {{ old('status', $admin->status->value) === $status->value ? 'selected' : '' }}>
                        {{ ucfirst($status->value) }}
                    </option>
                @endforeach
            </select>
            @if ($admin->id === Auth::guard('admin')->id())
                <input type="hidden" name="status" value="{{ $admin->status->value }}">
                <p style="font-size:.8rem; color:#6b7280;">You cannot change your own status.</p>
            @endif

            <fieldset>
                <legend>Roles</legend>
                <div class="checkbox-grid">
                    @foreach ($roles as $role)
                        <label>
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                {{ in_array($role->name, old('roles', $admin->roles->pluck('name')->all())) ? 'checked' : '' }}>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button type="submit" class="btn" style="margin-top:1rem;">Save</button>
        </form>
    </div>
@endsection
