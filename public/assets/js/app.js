// JS minimal: mencegah double-submit dan tombol salin QR content.
(function () {
    'use strict';

    // Cegah double-submit (penting untuk request yang menghasilkan transaksi).
    document.querySelectorAll('.js-submit-once').forEach(function (form) {
        form.addEventListener('submit', function () {
            var buttons = form.querySelectorAll('button[type="submit"]');
            buttons.forEach(function (btn) {
                btn.disabled = true;
            });
        });
    });

    // Tombol salin teks (QR content).
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.getAttribute('data-copy-target'));
            if (!target) {
                return;
            }
            var text = target.textContent;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    flashCopied(btn);
                }).catch(function () {
                    fallbackCopy(btn, text);
                });
            } else {
                fallbackCopy(btn, text);
            }
        });
    });

    function flashCopied(btn) {
        var original = btn.textContent;
        btn.textContent = 'Tersalin!';
        setTimeout(function () {
            btn.textContent = original;
        }, 1500);
    }

    function fallbackCopy(btn, text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            flashCopied(btn);
        } catch (e) {
            btn.textContent = 'Gagal';
        }
        document.body.removeChild(ta);
    }
})();
