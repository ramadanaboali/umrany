{{-- Pre-paint dark/light mode boot. Must be an inline <script> in <head>, before any stylesheet
     <link>, so the mode is applied before first paint — a @vite-loaded module script is deferred
     and would cause a flash of the wrong theme. Deliberately replaces Velzon's own assets/js/layout.js
     (see docs/decisions/0021-velzon-material-admin-theme.md): that script snapshots every <html>
     attribute into sessionStorage and does sessionStorage.clear() + location.reload() the moment
     the attribute set changes — which our server-rendered dir/lang swap on every locale change
     would trigger, silently wiping this very setting.

     $savedMode (from App\Support\AdminTheme::savedMode(), null for a guest) is the admin's
     DB-persisted preference — it wins over localStorage so the mode actually follows the account
     across browsers/devices, same priority order as language. localStorage stays the source of
     truth only when there's no signed-in admin (or no saved value yet) to defer to. --}}
@php $savedMode = \App\Support\AdminTheme::savedMode(); @endphp
<script>
    (function () {
        var saved = @json($savedMode?->value);
        var mode = saved;
        if (mode !== 'light' && mode !== 'dark') {
            mode = localStorage.getItem('umrany.admin.layout-mode');
        }
        if (mode !== 'light' && mode !== 'dark') {
            mode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-layout-mode', mode);
        localStorage.setItem('umrany.admin.layout-mode', mode);
        // Mirror into sessionStorage so Velzon's own assets/js/app.js (loaded later) reads the
        // same value back out instead of overriding it with its own default.
        try { sessionStorage.setItem('data-layout-mode', mode); } catch (e) {}
    })();
</script>
