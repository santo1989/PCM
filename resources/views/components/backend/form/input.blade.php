@props(['name', 'label'])

<div class="form-group mb-3">
    @if ($label ?? false)
        <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    @endif
    <input name="{{ $name }}" id="{{ $name }}" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    <x-backend.form.error :name="$name" />
</div>
