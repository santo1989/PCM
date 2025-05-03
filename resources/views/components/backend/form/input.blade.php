@props(['name', 'label'])

<div class="form-group">
    <label for="{{ $name }}">{{ $label ?? '' }}</label>
    <input name="{{ $name }}" class="form-control" id="{{ $name }}" {{ $attributes }}>
</div>
