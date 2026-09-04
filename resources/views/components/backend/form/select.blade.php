@props(['name', 'label', 'options', 'selected' => ''])

<div class="form-group mb-3">
    @if ($label ?? false)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $name }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @foreach ($options as $key => $value)
            <option value="{{ $key }}" {{ (string) $key === (string) $selected ? 'selected' : '' }}>{{ $value }}</option>
        @endforeach
    </select>
    <x-backend.form.error :name="$name" />
</div>
