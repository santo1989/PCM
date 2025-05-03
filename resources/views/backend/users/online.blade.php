<x-backend.layouts.master>
    <meta http-equiv="refresh" content="05; url={{ route('online_user') }}">
    {{-- // refresh page every 10 seconds --}}

    <div class="container">
        <h1>Online Users - List</h1>

        <table class="table table-bordered data-table" id="users_table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Last Seen</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>

                @foreach ($users as $user)
                    {{-- @dd(Cache::has('user-is-online-' . $user->id)) --}}
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            {{ Carbon\Carbon::parse($user->last_seen)->diffForHumans() }}
                        </td>
                        <td>
                            @if (Cache::has('user-is-online-' . $user->id))
                                <span class="text-success">Online</span>
                            @else
                                <span class="text-secondary">Offline</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{-- @push('js')
<script>
$(document).ready(function() {
        $('.users_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('online_user') }}",
            columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex'
                },
                {
                    data: 'name',
                    name: 'name'
                },
                {
                    data: 'email',
                    name: 'email'
                },
                {
                    data: 'last_seen',
                    name: 'last_seen'
                },
                {
                    data: 'status',
                    name: 'status'
                },
            ]
        });

        setInterval(function() {
            $('.users_table').DataTable().ajax.reload();
        }, 5000);


    });
</script> 
@endpush --}}
</x-backend.layouts.master>
