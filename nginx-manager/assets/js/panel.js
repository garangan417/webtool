/* =========================================================
   Nginx Manager - Panel JS
   ========================================================= */

(function () {
    'use strict';

    // Konfirmasi untuk aksi destruktif
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var msg = el.getAttribute('data-confirm') || 'Yakin?';
            if (!window.confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    });

    // Auto-dismiss alert setelah 5 detik
    document.querySelectorAll('.alert-auto-dismiss').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 5000);
    });

    // Tampilkan nama file yang dipilih di custom file input
    document.querySelectorAll('.form-file-custom input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var label = input.parentElement.querySelector('.file-label');
            if (label) {
                label.textContent = input.files[0] ? input.files[0].name : 'Pilih file...';
            }
        });
    });
})();