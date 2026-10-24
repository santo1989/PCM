@props(['name', 'label', 'id' => null])

@php($fieldId = $id ?? $name)

<div class="form-group mb-3">
    @if ($label ?? false)
        <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
    @endif
    <input name="{{ $name }}" id="{{ $fieldId }}" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    <x-backend.form.error :name="$name" />
</div>
