/*
 * Excel / PDF / Word download of the page's visible content.
 * Region: [data-export-region], else .sheet (invoice), else #app-main. Buttons go into [data-export-bar] or the top of the region.
 * Word copies only <style data-export-style>: browser extensions inject large styles into every page.
 * shortcut: exports what is on screen, so a paginated data table gives only its current page; filter to "all" first.
 */
(function () {
    var region = document.querySelector('[data-export-region]') || document.querySelector('.sheet') || document.getElementById('app-main');
    if (!region) return;

    var LIBS = {
        xlsx: 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js',
        pdf: 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'
    };
    var css = document.createElement('style');
    css.textContent = '.pcm-exporting .no-print,.pcm-exporting .pcm-export-bar,.pcm-exporting button,.pcm-exporting .btn,' +
        '.pcm-exporting input,.pcm-exporting select,.pcm-exporting textarea,.pcm-exporting .datatable-top,' +
        '.pcm-exporting .datatable-bottom,.pcm-exporting .pagination,.pcm-exporting .visually-hidden-focusable{display:none!important}' +
        '.pcm-exporting .table-responsive{overflow:visible!important}';
    document.head.appendChild(css);

    var name = (document.title || 'page').replace(/[^\w\- ]+/g, ' ').trim().replace(/\s+/g, '_').slice(0, 80) || 'page';

    function load(src) {
        return new Promise(function (ok, fail) {
            if (document.querySelector('script[src="' + src + '"]')) return ok();
            var s = document.createElement('script');
            s.src = src; s.onload = ok; s.onerror = function () { fail(new Error('Could not load ' + src)); };
            document.head.appendChild(s);
        });
    }
    function hidden(el) { return el.tagName !== 'BR' && el.getClientRects().length === 0; }
    // Runs fn while the export-only CSS hides buttons and forms.
    function exporting(fn) {
        document.body.classList.add('pcm-exporting');
        return Promise.resolve().then(fn).finally(function () { document.body.classList.remove('pcm-exporting'); });
    }
    function save(blob, file) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = file;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
    }
    function cell(text) {
        var t = text.replace(/\s+/g, ' ').trim();
        return /^-?[\d,]+(\.\d+)?$/.test(t) && /\d/.test(t) ? Number(t.replace(/,/g, '')) : t;
    }

    // Visible rows in reading order: each table row stays a row, other blocks become one row per text line.
    function rows(root, out) {
        Array.prototype.forEach.call(root.children, function (el) {
            if (hidden(el)) return;
            if (el.tagName === 'TABLE') {
                Array.prototype.forEach.call(el.rows, function (tr) {
                    if (hidden(tr)) return;
                    out.push(Array.prototype.map.call(tr.cells, function (c) { return cell(c.innerText); }));
                });
                out.push([]);
            } else if (el.querySelector('table')) {
                rows(el, out);
            } else {
                el.innerText.split('\n').forEach(function (l) { if (l.trim()) out.push([cell(l)]); });
            }
        });
        return out;
    }

    function excel() {
        return load(LIBS.xlsx).then(function () {
            return exporting(function () {
                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(rows(region, [])), 'Page');
                XLSX.writeFile(wb, name + '.xlsx');
            });
        });
    }

    function pdf() {
        return load(LIBS.pdf).then(function () {
            return exporting(function () {
                return html2pdf().set({
                    margin: 8, filename: name + '.pdf',
                    image: { type: 'jpeg', quality: 0.95 },
                    html2canvas: { scale: 2, useCORS: true },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: region.scrollWidth > 1000 ? 'landscape' : 'portrait' },
                    pagebreak: { mode: ['css', 'legacy'], avoid: 'tr' }
                }).from(region).save();
            });
        });
    }

    function word() {
        return exporting(function () {
            var marked = Array.prototype.filter.call(region.querySelectorAll('*'), hidden);
            marked.forEach(function (el) { el.setAttribute('data-pcm-hide', ''); });
            var copy = region.cloneNode(true);
            marked.forEach(function (el) { el.removeAttribute('data-pcm-hide'); });
            copy.querySelectorAll('[data-pcm-hide]').forEach(function (el) { el.remove(); });
            // Charts: swap each canvas for a picture of it.
            var live = region.querySelectorAll('canvas');
            copy.querySelectorAll('canvas').forEach(function (c, i) {
                var img = document.createElement('img');
                try { img.src = live[i].toDataURL('image/png'); img.width = Math.min(live[i].width, 640); c.replaceWith(img); } catch (e) { c.remove(); }
            });
            var styles = Array.prototype.map.call(document.querySelectorAll('style[data-export-style]'), function (s) { return s.textContent; }).join('\n');
            var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">' +
                '<head><meta charset="utf-8"><title>' + document.title.replace(/</g, '&lt;') + '</title><style>' + styles +
                'body{font-family:Calibri,Arial,sans-serif;font-size:11pt}table{border-collapse:collapse;width:100%}' +
                'th,td{border:1px solid #999;padding:4px 6px;vertical-align:top}</style></head><body>' + copy.innerHTML + '</body></html>';
            save(new Blob(['﻿', html], { type: 'application/msword' }), name + '.doc');
        });
    }

    var bar = document.querySelector('[data-export-bar]');
    if (!bar) {
        bar = document.createElement('div');
        bar.className = 'd-flex justify-content-end mb-2';
        region.insertBefore(bar, region.firstChild);
    }
    bar.classList.add('pcm-export-bar', 'no-print');
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Download this page');
    [['Excel', excel, 'bi-file-earmark-excel', 'success'], ['PDF', pdf, 'bi-file-earmark-pdf', 'danger'], ['Word', word, 'bi-file-earmark-word', 'primary']]
        .forEach(function (b) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-' + b[3] + ' ms-1';
            btn.innerHTML = '<i class="bi ' + b[2] + '" aria-hidden="true"></i> ' + b[0];
            btn.addEventListener('click', function () {
                btn.disabled = true;
                b[1]().catch(function (e) { alert('Download failed: ' + e.message); }).finally(function () { btn.disabled = false; });
            });
            bar.appendChild(btn);
        });
})();
