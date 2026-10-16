{{-- Ctrl/⌘+K palette. Behaviour lives in ui/backend/js/app.js; results come from route('search'). --}}
<div class="modal fade search-palette" id="searchPalette" tabindex="-1" aria-label="Search" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                <i class="bi bi-search text-muted"></i>
                <input type="search" class="form-control" id="searchPaletteInput" placeholder="Search transactions, categories, pages…"
                    autocomplete="off" aria-label="Search" aria-controls="searchPaletteResults">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="search-results py-1" id="searchPaletteResults" role="listbox">
                <div class="px-3 py-4 text-center small text-muted">Type to search. Use ↑ ↓ and Enter.</div>
            </div>
        </div>
    </div>
</div>
