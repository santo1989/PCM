/*
 * Live listings: debounced search/filter without full reloads, AJAX pagination, and
 * automatic refresh when data changes elsewhere (polls /live/signature).
 *
 * Markup contract:
 *   <form data-live-filter> ... </form>          GET filter form (text inputs debounce, selects/dates fire at once)
 *   <div  data-live-region id="unique-id"> </div> re-rendered from the server response by id
 */
(function () {
    'use strict';

    var regionsSelector = '[data-live-region]';
    if (!document.querySelector(regionsSelector)) return;

    var inflight = null;

    // Server-generated URLs use APP_URL, which may differ from the host/port the browser is on
    // (localhost vs 127.0.0.1:8000, subfolder installs). Always call the current origin.
    function norm(url) {
        var u = new URL(url, location.href);
        return location.origin + u.pathname + u.search + u.hash;
    }

    function reinit(scope) {
        if (window.jQuery && jQuery.fn.select2) {
            jQuery(scope).find('select.select2').each(function () {
                if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                    jQuery(this).select2({ theme: 'bootstrap-5', width: '100%' });
                }
            });
        }
    }

    function setBusy(on) {
        document.querySelectorAll(regionsSelector).forEach(function (el) {
            el.style.opacity = on ? '0.55' : '';
            el.style.transition = 'opacity .15s';
        });
    }

    function load(url, opts) {
        opts = opts || {};
        url = norm(url);
        if (inflight) inflight.abort();
        inflight = new AbortController();
        if (!opts.silent) setBusy(true);

        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            signal: inflight.signal,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var swapped = 0;
                document.querySelectorAll(regionsSelector).forEach(function (el) {
                    var fresh = doc.getElementById(el.id);
                    if (!fresh) return;
                    // dispose bootstrap modals living in the old region before replacing them
                    el.querySelectorAll('.modal').forEach(function (m) {
                        var inst = window.bootstrap && bootstrap.Modal.getInstance(m);
                        if (inst) inst.dispose();
                    });
                    el.innerHTML = fresh.innerHTML;
                    reinit(el);
                    swapped++;
                });
                if (!swapped) { location.href = url; return; }
                if (!opts.silent) {
                    try { history.replaceState(null, '', url); } catch (e) { }
                }
            })
            .catch(function (e) { if (e.name !== 'AbortError') console.warn('live load failed', e); })
            .then(function () { setBusy(false); });
    }

    function formUrl(form) {
        var params = new URLSearchParams(new FormData(form));
        params.delete('_token');
        params.delete('page');
        Array.from(params.keys()).forEach(function (k) { if (params.get(k) === '') params.delete(k); });
        var qs = params.toString();
        return norm(form.getAttribute('action')) + (qs ? '?' + qs : '');
    }

    function bindForm(form) {
        var t;
        var go = function () { clearTimeout(t); load(formUrl(form)); };
        form.addEventListener('input', function (e) {
            if (e.target.matches('input[type=text],input[type=search],input:not([type])')) {
                clearTimeout(t);
                t = setTimeout(go, 350);
            }
        });
        form.addEventListener('change', function (e) {
            if (!e.target.matches('input[type=text],input[type=search],input:not([type])')) go();
        });
        form.addEventListener('submit', function (e) { e.preventDefault(); go(); });
        // select2 fires jQuery events, not native ones
        if (window.jQuery) {
            jQuery(form).on('select2:select select2:unselect select2:clear', go);
        }
    }

    document.querySelectorAll('form[data-live-filter]').forEach(bindForm);

    // Excel/export links: rebuild from the live filter state so they never go stale
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-live-export]');
        var form = document.querySelector('form[data-live-filter]');
        if (!a || !form) return;
        var u = formUrl(form);
        a.href = u + (u.indexOf('?') > -1 ? '&' : '?') + 'export_format=xlsx';
    });

    // AJAX pagination
    document.addEventListener('click', function (e) {
        var a = e.target.closest(regionsSelector + ' .pagination a');
        if (!a || !a.href) return;
        e.preventDefault();
        load(a.href);
    });

    // Realtime: refresh when the server-side data signature changes.
    var lastSig = null;
    var pending = false;

    function userIsBusy() {
        if (document.querySelector('.modal.show')) return true;
        var f = document.activeElement;
        return !!(f && f.closest && f.closest(regionsSelector) && f.matches('input,select,textarea'));
    }

    function refreshNow() {
        pending = false;
        load(location.pathname + location.search, { silent: true }).then(function () {
            if (window.Swal) {
                Swal.fire({ toast: true, position: 'bottom-end', icon: 'info', title: 'Data updated',
                    showConfirmButton: false, timer: 1800 });
            }
        });
    }

    function poll() {
        if (document.hidden) return;
        fetch(norm(window.LIVE_SIG_URL || '/live/signature'), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) return;
                if (lastSig === null) { lastSig = d.sig; return; }
                if (d.sig !== lastSig) {
                    lastSig = d.sig;
                    if (userIsBusy()) pending = true; else refreshNow();
                } else if (pending && !userIsBusy()) {
                    refreshNow();
                }
            })
            .catch(function () { });
    }

    poll();
    setInterval(poll, 10000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
