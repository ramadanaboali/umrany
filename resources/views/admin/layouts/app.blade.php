<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Dashboard') · Umrany Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth('admin')
        <meta name="admin-id" content="{{ Auth::guard('admin')->id() }}">
    @endauth
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
    {{-- Swap this block for the real dashboard theme's stylesheet/script links — nothing in the
         markup below (sidebar/nav/content structure) depends on this specific CSS, it's a
         plain, theme-agnostic placeholder standing in until the theme is integrated. --}}
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; display: flex; min-height: 100vh; background: #f4f5f7; }
        nav { width: 220px; background: #111827; color: #d1d5db; padding: 1.5rem 1rem; flex-shrink: 0; }
        nav .brand { color: #fff; font-weight: 600; margin-bottom: 1.5rem; display: block; }
        nav a { display: block; color: #d1d5db; text-decoration: none; padding: .5rem .5rem; border-radius: 4px; font-size: .9rem; }
        nav a:hover, nav a.active { background: #1f2937; color: #fff; }
        main { flex: 1; padding: 2rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        h1 { font-size: 1.4rem; margin: 0; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; }
        th, td { text-align: left; padding: .6rem .8rem; border-bottom: 1px solid #eee; font-size: .9rem; }
        th { background: #f9fafb; font-weight: 600; }
        .btn { display: inline-block; padding: .45rem .9rem; border-radius: 4px; background: #1d4ed8; color: #fff; text-decoration: none; font-size: .85rem; border: none; cursor: pointer; }
        .btn-secondary { background: #6b7280; }
        .card { background: #fff; padding: 1.5rem; border-radius: 6px; max-width: 640px; }
        label { display: block; font-size: .85rem; margin: .75rem 0 .25rem; }
        input[type=text], input[type=email], input[type=password], input[type=tel], select { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; }
        .badge { display: inline-block; padding: .15rem .5rem; border-radius: 3px; font-size: .75rem; background: #e5e7eb; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-suspended { background: #fee2e2; color: #991b1b; }
        .status { background: #ecfdf5; color: #065f46; padding: .5rem .75rem; border-radius: 4px; font-size: .85rem; margin-bottom: 1rem; }
        .errors { background: #fef2f2; color: #991b1b; padding: .5rem .75rem; border-radius: 4px; font-size: .85rem; margin-bottom: 1rem; }
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .35rem; margin-top: .5rem; }
        fieldset { border: 1px solid #e5e7eb; border-radius: 6px; margin-top: 1rem; padding: .75rem; }
        legend { font-weight: 600; font-size: .85rem; text-transform: capitalize; }
    </style>
</head>
<body>
    <nav>
        <span class="brand">Umrany Admin</span>
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        @can('admins.list')
            <a href="{{ route('admin.admins.index') }}">Admins</a>
        @endcan
        @can('roles.list')
            <a href="{{ route('admin.roles.index') }}">Roles</a>
            <a href="{{ route('admin.permissions.index') }}">Permissions</a>
        @endcan
        <a href="{{ route('admin.profile.edit') }}">My Profile</a>
        <form method="POST" action="{{ route('admin.logout') }}" style="margin-top:1rem;">
            @csrf
            <button type="submit" class="btn btn-secondary" style="width:100%;">Log out</button>
        </form>
    </nav>
    <main>
        <header>
            <h1>@yield('title', 'Dashboard')</h1>
        </header>
        <div id="permissions-changed-banner" class="status" hidden style="background:#fef3c7; color:#92400e;">
            Your permissions have changed —
            <a href="{{ request()->fullUrl() }}">refresh this page</a> to see the update.
            <button type="button" onclick="this.closest('div').hidden = true;" style="float:right; background:none; border:none; cursor:pointer; font-size:1rem; color:inherit;">&times;</button>
        </div>
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="errors">
                <ul style="margin:0; padding-left:1.1rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
