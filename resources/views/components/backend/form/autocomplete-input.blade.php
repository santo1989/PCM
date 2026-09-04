@props(['name', 'label' => null, 'model', 'column', 'value' => ''])

@php
    // Stable per field+model, so cloned "add row" inputs on the same page can all
    // point at the same <datalist> id regardless of row index.
    $listId = 'dl_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $model . '_' . $column);
    $suggestions = \App\Services\AutocompleteSource::values($model, $column);
@endphp

<div class="form-group mb-3">
    @if ($label)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <input list="{{ $listId }}" name="{{ $name }}" value="{{ $value }}" autocomplete="off"
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    <datalist id="{{ $listId }}">
        @foreach ($suggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>
    <x-backend.form.error :name="$name" />
</div>
