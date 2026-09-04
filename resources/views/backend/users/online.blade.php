<x-backend.layouts.master>
    <x-slot name="pageTitle">
        Online Users
    </x-slot>

    <meta http-equiv="refresh" content="15; url={{ route('online_user') }}">

    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Online Users</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
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
                            @forelse ($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ \Carbon\Carbon::parse($user->last_seen)->diffForHumans() }}</td>
                                    <td>
                                        @if (\Illuminate\Support\Facades\Cache::has('user-is-online-' . $user->id))
                                            <span class="badge bg-success">Online</span>
                                        @else
                                            <span class="badge bg-secondary">Offline</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</x-backend.layouts.master>
