@props(['errors'])

{{-- @if ($errors->any())
<div class="alert alert-danger">
    <ul>
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif --}}

@if ($errors->any())
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html: (@json($errors->all())).join('<br>'),
        });
    </script>
@endif