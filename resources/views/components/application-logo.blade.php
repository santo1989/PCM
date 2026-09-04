@props(['light' => false])

<div {{ $attributes->class(['d-flex align-items-center justify-content-center gap-2 fs-3 fw-bold', 'text-white' => $light, 'text-primary' => !$light]) }}>
    <i class="bi bi-wallet2"></i>
    <span>Personal Cost Management</span>
</div>
