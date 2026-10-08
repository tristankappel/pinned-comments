/**
 * Pinned Comments – Admin JavaScript
 * Vanilla JS, no external libraries.
 */
(function () {
    'use strict';

    var data = window.pcAdmin || {};
    var ajaxUrl = data.ajaxUrl;
    var nonce = data.nonce;
    var i18n = data.i18n || {};

    function init() {
        if (window.jQuery && window.jQuery.fn.wpColorPicker) {
            window.jQuery('.pc-color-picker').wpColorPicker();
        }

        var btn = document.getElementById('pc-delete-all-btn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            if (!confirm(i18n.confirmDeleteAll || 'Are you sure?')) return;

            btn.classList.add('loading');
            btn.disabled = true;

            var body = 'action=pc_delete_all&nonce=' + encodeURIComponent(nonce);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.onreadystatechange = function () {
                btn.classList.remove('loading');
                btn.disabled = false;

                if (xhr.readyState !== 4) return;

                var msgEl = document.getElementById('pc-admin-message');
                if (!msgEl) {
                    msgEl = document.createElement('div');
                    msgEl.id = 'pc-admin-message';
                    msgEl.className = 'pc-admin-message';
                    btn.parentNode.appendChild(msgEl);
                }

                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            msgEl.className = 'pc-admin-message success';
                            msgEl.textContent = i18n.deleted || 'All comments deleted.';
                            ['pc-total-count', 'pc-desktop-count', 'pc-mobile-count'].forEach(function (id) {
                                var countEl = document.getElementById(id);
                                if (countEl) countEl.textContent = '0';
                            });
                        } else {
                            msgEl.className = 'pc-admin-message error';
                            msgEl.textContent = (res.data && res.data.message) || (i18n.error || 'An error occurred.');
                        }
                    } catch (e) {
                        msgEl.className = 'pc-admin-message error';
                        msgEl.textContent = i18n.error || 'An error occurred.';
                    }
                } else {
                    msgEl.className = 'pc-admin-message error';
                    msgEl.textContent = i18n.error || 'An error occurred.';
                }
            };
            xhr.send(body);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
