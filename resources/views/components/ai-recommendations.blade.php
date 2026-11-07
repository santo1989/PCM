{{--
    <x-ai-recommendations page="stocks" :metrics="$m" :template="['insights' => [...], 'actions' => [...]]" :aliases="['Client 1' => 'REAL NAME']" />
    Rule-based text shows at once; the free-AI version (App\Services\AI\AiRecommendations) replaces it when ready.
    aliases: placeholders sent to the AI instead of private names, swapped back only here in the browser.
--}}
@props(['page', 'metrics', 'template', 'aliases' => [], 'title' => 'AI recommendations'])
@php
    $r = \App\Services\AI\AiRecommendations::get($page, $metrics, $template);
    $real = fn($s) => strtr($s, $aliases);
    $boxId = 'ai-rec-' . $r['id'];
@endphp
<div class="card mt-3 ai-recommendations" id="{{ $boxId }}" @if ($r['pending']) data-ai-pending="{{ route('ai.recommendations', $r['id']) }}" @endif
    data-aliases='@json((object) $aliases)'>
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span><i class="bi bi-stars"></i> {{ $title }}</span>
        <span class="small text-muted" data-ai-source>
            @if ($r['pending'])
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Asking the free AI…
            @elseif ($r['source'] === 'template')
                Rule-based{{ $r['rejected'] ? ' (AI answer not used: ' . $r['rejected'] . ')' : '' }}
            @else
                Written by {{ $r['model'] }} · numbers checked against your data
            @endif
        </span>
    </div>
    <div class="card-body">
        <ul class="mb-2" data-ai-insights>@foreach ($r['insights'] as $s)<li>{{ $real($s) }}</li>@endforeach</ul>
        <ul class="mb-0 small list-unstyled" data-ai-actions>@foreach ($r['actions'] as $s)<li class="mb-1"><i class="bi bi-check2-circle text-success"></i> <strong>Do:</strong> {{ $real($s) }}</li>@endforeach</ul>
    </div>
</div>
@if ($r['pending'])
    <script>
        (function () {
            var box = document.getElementById(@json($boxId));
            var aliases = JSON.parse(box.dataset.aliases || '{}');
            var real = function (s) { Object.keys(aliases).forEach(function (k) { s = s.split(k).join(aliases[k]); }); return s; };
            var item = function (s, action) {
                var li = document.createElement('li');
                if (action) { li.className = 'mb-1'; li.innerHTML = '<i class="bi bi-check2-circle text-success"></i> <strong>Do:</strong> '; }
                li.appendChild(document.createTextNode(real(s)));
                return li;
            };
            fetch(box.dataset.aiPending, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) {
                    var ins = box.querySelector('[data-ai-insights]'), act = box.querySelector('[data-ai-actions]');
                    ins.replaceChildren.apply(ins, d.insights.map(function (s) { return item(s, false); }));
                    act.replaceChildren.apply(act, d.actions.map(function (s) { return item(s, true); }));
                    box.querySelector('[data-ai-source]').textContent = d.source === 'template'
                        ? 'Rule-based' + (d.rejected ? ' (AI answer not used: ' + d.rejected + ')' : '')
                        : 'Written by ' + d.model + ' · numbers checked against your data';
                })
                .catch(function () { box.querySelector('[data-ai-source]').textContent = 'Rule-based (AI not reachable)'; });
        })();
    </script>
@endif
