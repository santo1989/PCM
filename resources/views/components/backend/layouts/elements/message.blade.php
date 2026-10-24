@props(['message', 'type' => 'success'])

@if ($message)
    <script>
        Swal.fire({
            icon: @json($type),
            title: @json(ucfirst($type)),
            text: @json($message),
            timer: 2500,
            showConfirmButton: false,
        });
    </script>
@endif
