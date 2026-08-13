<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Admin') · Umrany</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Swap this block for the real dashboard theme's stylesheet link(s) — nothing in the
         markup below depends on this specific CSS, it's a plain, theme-agnostic placeholder. --}}
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f4f5f7; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); width: 100%; max-width: 380px; }
        h1 { font-size: 1.25rem; margin: 0 0 1.25rem; }
        label { display: block; font-size: .85rem; margin: .75rem 0 .25rem; color: #333; }
        input[type=email], input[type=password], input[type=text] { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { margin-top: 1.25rem; width: 100%; padding: .6rem; background: #1d4ed8; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .status { background: #ecfdf5; color: #065f46; padding: .5rem .75rem; border-radius: 4px; font-size: .85rem; margin-bottom: 1rem; }
        .error { color: #b91c1c; font-size: .8rem; margin-top: .25rem; }
        .links { margin-top: 1rem; font-size: .85rem; text-align: center; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
</body>
</html>
