<x-backend.layouts.master>
    <x-slot name="pageTitle">
        User List
    </x-slot>

    <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> User </x-slot>
            <li class="breadcrumb-item"><a href="{{ route('users.index') }}">User</a></li>
            <li class="breadcrumb-item active">User</li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot>

    <section class="content">
        <div class="container-fluid">

            @if (session('message'))
                <div class="alert alert-success">
                    <span class="close" data-dismiss="alert">&times;</span>
                    <strong>{{ session('message') }}.</strong>
                </div>
            @endif

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            {{-- <div class='d-flex justify-content-between '>
                  <form method="GET" action="{{ route('users.index') }}">
                      <input type="text" name="search" placeholder="Search" class="form-control" style="width: 200px;">
                  </form>&nbsp;&nbsp;
      
                  <form method="GET" action="{{ route('users.index') }}" >
                      <select name="role_id" id="role_id" class="form-select">
                          @foreach ($roles as $role)
                              <option value="{{ $role->id }}">{{ $role->name }}</option>
                          @endforeach
                      
                      </select>
                      <button class="btn btn-primary" type="submit">Role Search</button>
                  </form>
              </div> --}}

                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            {{-- role Table goes here --}}
                            @php
                                $supervisor = App\Models\SupervisorAssign::where('supervisor_id', Auth::user()->id)->get();
                                // dd($supervisor);
                                
                                $user_id = [];
                                
                                foreach ($supervisor as $supervisor) {
                                    $user_id[] = $supervisor->user_id;
                                }
                                
                                $users = App\Models\User::whereIn('id', $user_id)->get();
                                // dd($users);
                            @endphp
                            <table id="datatablesSimple" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Sl#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $sl=0 @endphp
                                    @foreach ($users as $user)
                                        <tr>
                                            <td>{{ ++$sl }}</td>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->email }}</td>
                                            <td>{{ $user->role->name }}</td>
                                            <td>
                                                {{-- <a class="btn btn-info btn-sm"
                                                    href="{{ route('users.show', ['user' => $user->id]) }}" style="background-image: linear-gradient(#40c47c,#40c47c,#40c47c);display:inline;">Profile</a>

                                                <a class="btn btn-warning btn-sm"
                                                    href="{{ route('users.edit', ['user' => $user->id]) }}"style="display:inline">Edit</a> --}}

                                                <x-backend.form.anchor :href="route('users.show', ['user' => $user->id])" type="show" />



                                                <x-backend.form.anchor :href="route('users.edit', ['user' => $user->id])" type="edit" />

                                                <form style="display:inline"
                                                    action="{{ route('users.destroy', ['user' => $user->id]) }}"
                                                    method="post">
                                                    @csrf
                                                    @method('delete')

                                                    <button onclick="return confirm('Are you sure want to delete ?')"
                                                        class="btn btn-sm btn-outline-danger" type="submit"><i
                                                            class="bi bi-trash"></i> Delete</button>
                                                </form>

                                            </td>
                                        </tr>
                                    @endforeach

                                </tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->


                    <!-- /.card -->
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </section>


</x-backend.layouts.master>
