@livewireScripts
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>

<script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
<script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>

<script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script>

<script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>

<script src="{{ asset('assets/vendor/libs/hammer/hammer.js') }}"></script>

<script src="{{ asset('assets/vendor/libs/i18n/i18n.js') }}"></script>

<script src="{{ asset('assets/vendor/js/menu.js') }}"></script>

<!-- endbuild -->

<!-- Vendors JS -->
<script src="{{ asset('assets/vendor/libs/@form-validation/popular.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/@form-validation/bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/@form-validation/auto-focus.js') }}"></script>

<!-- Vendors JS -->
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/tagify/tagify.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/bootstrap-select/bootstrap-select.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/bloodhound/bloodhound.js') }}"></script>

<!-- Main JS -->
<script src="{{ asset('assets/js/main.js') }}"></script>

<!-- Page JS -->
<script src="{{ asset('assets/js/pages-auth.js') }}"></script>
<script src="{{ asset('assets/js/forms-selects.js') }}"></script>
<script src="{{ asset('assets/js/forms-tagify.js') }}"></script>
<script src="{{ asset('assets/js/forms-typeahead.js') }}"></script>

<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.js') }}"></script>
{{-- <script src="{{ asset('assets/vendor/libs/jquery-timepicker/jquery-timepicker.js') }}"></script> --}}
{{-- <script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script> --}}
{{-- <script src="{{ asset('assets/js/forms-pickers.js') }}"></script> --}}
<script>
    document.addEventListener('DOMContentLoaded', initializeAdminMobileMenu);
    document.addEventListener('livewire:navigated', initializeAdminMobileMenu);

    function initializeAdminMobileMenu() {
        const html = document.documentElement;
        const overlay = document.querySelector('.layout-overlay');
        const toggles = document.querySelectorAll('.layout-menu-toggle');
        const mobileBreakpoint = 1200;

        if (!overlay || !toggles.length) {
            return;
        }

        toggles.forEach(toggle => {
            if (toggle.dataset.mobileMenuBound === 'true') {
                return;
            }

            toggle.dataset.mobileMenuBound = 'true';
            toggle.addEventListener('click', event => {
                if (window.innerWidth >= mobileBreakpoint) {
                    return;
                }

                event.preventDefault();
                html.classList.toggle('layout-menu-expanded');
            });
        });

        if (overlay.dataset.mobileMenuBound !== 'true') {
            overlay.dataset.mobileMenuBound = 'true';
            overlay.addEventListener('click', () => {
                html.classList.remove('layout-menu-expanded');
            });
        }

        if (window.__adminMobileMenuResizeBound !== true) {
            window.__adminMobileMenuResizeBound = true;
            window.addEventListener('resize', () => {
                if (window.innerWidth >= mobileBreakpoint) {
                    html.classList.remove('layout-menu-expanded');
                }
            });
        }
    }
</script>
