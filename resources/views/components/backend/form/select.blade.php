@props(['name', 'label', 'options', 'selected' => '', 'id' => null])

@php($fieldId = $id ?? $name)

<div class="form-group mb-3">
    @if ($label ?? false)
        <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $fieldId }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @foreach ($options as $key => $value)
            <option value="{{ $key }}" {{ (string) $key === (string) $selected ? 'selected' : '' }}>{{ $value }}</option>
        @endforeach
    </select>
    <x-backend.form.error :name="$name" />
</div>
