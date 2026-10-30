{{--
    For pages that change their date range by AJAX: wrap <x-analytics-context> in
    <div id="analyticsContext" data-url="{{ route('analytics.context') }}" data-from=".." data-to="..">
    and call reloadAnalyticsContext(start, end) after each refresh. Unchanged ranges are skipped,
    so auto-refresh timers do not refetch it.
--}}
<script>
    window.reloadAnalyticsContext = function (start, end) {
        const box = document.getElementById('analyticsContext');
        if (!box || !start || !end || (box.dataset.from === start && box.dataset.to === end)) return;
        box.dataset.from = start;
        box.dataset.to = end;
        box.style.opacity = .5;
        fetch(`${box.dataset.url}?start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.ok ? r.text() : Promise.reject(r.status))
            .then(html => { if (box.dataset.from === start && box.dataset.to === end) box.innerHTML = html; })
            .catch(() => { delete box.dataset.from; }) // allow a retry on the next refresh
            .finally(() => { box.style.opacity = 1; });
    };
</script>
