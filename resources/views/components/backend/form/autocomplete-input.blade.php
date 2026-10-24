@props(['name', 'label' => null, 'model', 'column', 'value' => ''])

@php
    // Stable per field+model, so cloned "add row" inputs on the same page can all
    // point at the same <datalist> id regardless of row index.
    $listId = 'dl_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $model . '_' . $column);
    // Every input sharing this list id can use one <datalist>; emit it once per page
    // (it was repeated in each row's edit modal: ~2 MB on Income & Expenses).
    $renderedLists = request()->attributes->get('rendered_datalists', []);
    $renderList = !isset($renderedLists[$listId]);
    if ($renderList) {
        request()->attributes->set('rendered_datalists', $renderedLists + [$listId => true]);
    }
    $suggestions = $renderList ? \App\Services\AutocompleteSource::values($model, $column) : [];
@endphp

<div class="form-group mb-3">
    @if ($label)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <input list="{{ $listId }}" name="{{ $name }}" value="{{ $value }}" autocomplete="off"
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    @if ($renderList)
        <datalist id="{{ $listId }}">
            @foreach ($suggestions as $suggestion)
                <option value="{{ $suggestion }}"></option>
            @endforeach
        </datalist>
    @endif
    <x-backend.form.error :name="$name" />
</div>
