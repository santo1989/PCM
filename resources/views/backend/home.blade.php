@switch(auth()->user()->role_id)
    @case('1')
        @include('layouts.Admin')
    @break

    @case('2')
        @include('layouts.User')
    @break

    @case('3')
        @include('layouts.Manager')
    @break
    @default
        <x-backend.layouts.master>


        </x-backend.layouts.master>
@endswitch

<script>
    $(document).ready(function() {
        $('#example').DataTable();
    } );
</script>
