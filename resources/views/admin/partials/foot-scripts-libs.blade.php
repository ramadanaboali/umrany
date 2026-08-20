{{-- Shared by both layouts. assets/js/app.js is NOT included here — it's the authenticated
     layout's sidebar/topbar behavior (menu collapse, dark-mode click handler, etc.), which the
     guest/login layout has none of. See docs/decisions/0021-velzon-material-admin-theme.md. --}}
<script src="{{ asset('vendor/velzon/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/velzon/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('vendor/velzon/libs/node-waves/waves.min.js') }}"></script>
<script src="{{ asset('vendor/velzon/libs/feather-icons/feather.min.js') }}"></script>
