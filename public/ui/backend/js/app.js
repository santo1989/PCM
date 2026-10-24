/*
 * PCM app shell behaviour: theme, sidebar, search palette, PWA, backup.
 * Page-specific scripts stay in their views; this file only owns the shell.
 */
(function () {
    'use strict';

    var PCM = window.PCM || { routes: {} };
    var csrf = document.querySelector('meta[name="csrf-token"]');
    csrf = csrf ? csrf.content : '';

    function store(key, value) {
        try {
            if (value === undefined) return localStorage.getItem(key);
            localStorage.setItem(key, value);
        } catch (e) { return null; }
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ---------- Theme ----------
    var root = document.documentElement;
    var themeBtn = document.getElementById('themeToggle');

    function syncThemeIcon() {
        if (!themeBtn) return;
        var dark = root.getAttribute('data-bs-theme') === 'dark';
        themeBtn.innerHTML = '<i class="bi ' + (dark ? 'bi-sun' : 'bi-moon-stars') + '"></i>';
        themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
    }

    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', next);
            store('pcm-theme', next);
            syncThemeIcon();
            document.dispatchEvent(new CustomEvent('pcm:theme', { detail: next }));
        });
        syncThemeIcon();
    }

    // Reports/PDFs always print in light mode.
    var themeBeforePrint = null;
    window.addEventListener('beforeprint', function () {
        themeBeforePrint = root.getAttribute('data-bs-theme');
        root.setAttribute('data-bs-theme', 'light');
    });
    window.addEventListener('afterprint', function () {
        if (themeBeforePrint) root.setAttribute('data-bs-theme', themeBeforePrint);
    });

    // Chart.js reads defaults when a chart updates, so re-theme existing charts in place.
    function applyChartTheme() {
        if (!window.Chart || !Chart.defaults) return;
        var dark = root.getAttribute('data-bs-theme') === 'dark';
        Chart.defaults.color = dark ? '#cbd5e1' : '#475569';
        Chart.defaults.borderColor = dark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.08)';
        Object.values(Chart.instances || {}).forEach(function (c) { c.update('none'); });
    }
    window.addEventListener('load', applyChartTheme);
    document.addEventListener('pcm:theme', applyChartTheme);

    // Report tabs scroll sideways on phones; bring the current one into view.
    var currentTab = document.querySelector('.report-nav [aria-current="page"]');
    if (currentTab) {
        var nav = currentTab.parentElement;
        nav.scrollLeft = currentTab.offsetLeft - (nav.clientWidth - currentTab.offsetWidth) / 2;
    }

    // ---------- Desktop sidebar collapse ----------
    var collapseBtn = document.getElementById('sidebarCollapseBtn');
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            var collapsed = document.body.classList.toggle('sidebar-collapsed');
            store('pcm-sidebar', collapsed ? 'collapsed' : 'open');
        });
    }

    // ---------- Open a modal from the URL: ?open=<modal id> (used by quick-add links) ----------
    var openId = new URLSearchParams(location.search).get('open');
    if (openId && /^[A-Za-z][\w-]*$/.test(openId)) {
        var target = document.getElementById(openId);
        if (target && target.classList.contains('modal') && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(target).show();
        }
    }

    // ---------- Select2 + simple-datatables (unchanged behaviour) ----------
    if (window.jQuery && jQuery.fn.select2) {
        jQuery(function ($) {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
        });
    }

    var dt = document.getElementById('datatablesSimple');
    if (dt && window.simpleDatatables) {
        new simpleDatatables.DataTable(dt);
    }

    // ---------- Search palette (Ctrl/Cmd + K) ----------
    var palette = document.getElementById('searchPalette');
    var input = document.getElementById('searchPaletteInput');
    var results = document.getElementById('searchPaletteResults');

    if (palette && input && results && window.bootstrap) {
        var modal = bootstrap.Modal.getOrCreateInstance(palette);
        var timer = null;
        var controller = null;
        var activeIndex = -1;

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                modal.show();
            } else if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)
                && !document.activeElement.isContentEditable) {
                e.preventDefault();
                modal.show();
            }
        });

        palette.addEventListener('shown.bs.modal', function () {
            input.focus();
            input.select();
        });

        function items() {
            return results.querySelectorAll('.search-item');
        }

        function setActive(i) {
            var list = items();
            if (!list.length) return;
            activeIndex = (i + list.length) % list.length;
            list.forEach(function (el, idx) {
                el.classList.toggle('active', idx === activeIndex);
                el.setAttribute('aria-selected', idx === activeIndex ? 'true' : 'false');
            });
            list[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        function render(groups) {
            var html = '';
            groups.forEach(function (g) {
                if (!g.items.length) return;
                html += '<div class="search-group">' + escapeHtml(g.label) + '</div>';
                g.items.forEach(function (it) {
                    html += '<a class="search-item" role="option" href="' + escapeHtml(it.url) + '">' +
                        '<i class="bi ' + escapeHtml(it.icon || 'bi-dot') + '"></i>' +
                        '<span class="flex-grow-1 text-truncate">' + escapeHtml(it.title) +
                        (it.subtitle ? '<br><small>' + escapeHtml(it.subtitle) + '</small>' : '') + '</span>' +
                        (it.meta ? '<small class="text-nowrap">' + escapeHtml(it.meta) + '</small>' : '') +
                        '</a>';
                });
            });
            results.innerHTML = html || '<div class="px-3 py-4 text-center small text-muted">No matches.</div>';
            activeIndex = -1;
            if (items().length) setActive(0);
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (!q) {
                results.innerHTML = '<div class="px-3 py-4 text-center small text-muted">Type to search. Use ↑ ↓ and Enter.</div>';
                return;
            }
            timer = setTimeout(function () {
                if (controller) controller.abort();
                controller = 'AbortController' in window ? new AbortController() : null;
                fetch(PCM.routes.search + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' },
                    signal: controller ? controller.signal : undefined
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { render(data.groups || []); })
                    .catch(function (err) {
                        if (err.name !== 'AbortError') {
                            results.innerHTML = '<div class="px-3 py-4 text-center small text-danger">Search failed. Try again.</div>';
                        }
                    });
            }, 180);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
            else if (e.key === 'Enter') {
                var list = items();
                if (list[activeIndex]) { e.preventDefault(); location.href = list[activeIndex].href; }
            }
        });
    }

    // ---------- PWA: service worker + install button ----------
    if ('serviceWorker' in navigator && PCM.serviceWorker) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(PCM.serviceWorker).catch(function () { /* offline support is optional */ });
        });
    }

    var installBtn = document.getElementById('installAppBtn');
    var deferredInstall = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredInstall = e;
        if (installBtn) installBtn.classList.remove('d-none');
    });
    if (installBtn) {
        installBtn.addEventListener('click', function () {
            if (!deferredInstall) return;
            deferredInstall.prompt();
            deferredInstall.userChoice.finally(function () {
                deferredInstall = null;
                installBtn.classList.add('d-none');
            });
        });
    }

    // ---------- Database backup: manual button + once-per-day automatic check ----------
    function runDatabaseBackup() {
        return fetch(PCM.routes.backupRun, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        }).then(function (res) { return res.json(); });
    }

    var backupBtn = document.getElementById('backupNowBtn');
    if (backupBtn && window.Swal) {
        backupBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Backing up database…',
                text: 'Please wait, this can take a moment.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () { Swal.showLoading(); }
            });
            runDatabaseBackup().then(function (data) {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Backup complete', timer: 2000, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Backup failed', text: data.message || 'Please try again.' });
                }
            }).catch(function () {
                Swal.fire({ icon: 'error', title: 'Backup failed', text: 'Could not reach the server.' });
            });
        });
    }

    // Only blocks the UI when a backup is actually still needed today; fails open if the DB is unreachable.
    if (PCM.routes.backupStatus && window.Swal) {
        fetch(PCM.routes.backupStatus, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.needed) return;
                Swal.fire({
                    title: 'Server backup and maintenance',
                    text: 'Please wait while we back up the database.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function () { Swal.showLoading(); }
                });
                runDatabaseBackup().finally(function () { Swal.close(); });
            })
            .catch(function () { /* fail open */ });
    }
})();
