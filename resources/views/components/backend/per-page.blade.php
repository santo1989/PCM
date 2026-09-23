@props(['default' => 25])
@php $current = (int) request('per_page', $default); @endphp
<div>
    <label class="form-label small mb-1">Rows</label>
    <select name="per_page" class="form-select form-select-sm" style="min-width:80px">
        @foreach ([10, 25, 50, 100] as $n)
            <option value="{{ $n }}" {{ $current === $n ? 'selected' : '' }}>{{ $n }} / page</option>
        @endforeach
    </select>
</div>
