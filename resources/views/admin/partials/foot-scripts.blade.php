{{-- Authenticated layout only. Deliberately omits Velzon's assets/js/layout.js (see
     partials/theme-mode-boot.blade.php), assets/js/plugins.js (document.writes a CDN <script>
     plus broken relative asset paths under any non-root path), the Lord Icon CDN loader, and
     every js/pages/*.init.js demo-page script — none of the current screens use charts/vector-
     maps/sliders. Add a specific init script on the specific view that needs it. See
     docs/decisions/0021-velzon-material-admin-theme.md. --}}
@include('admin.partials.foot-scripts-libs')
<script src="{{ asset('vendor/velzon/js/app.js') }}"></script>
