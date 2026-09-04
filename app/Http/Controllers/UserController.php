<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $usersCollection = User::latest()->get();

        if (request()->has('role_id')) {
            $usersCollection = $usersCollection
                ->where('role_id', request('role_id'));
        }

        if (request('search')) {
            $usersCollection = $usersCollection
                ->where('name', 'like', '%' . request('search') . '%');
        }

        $users = $usersCollection;
        // dd($users);
        $roles = Role::all();

        return view('backend.users.index', [
            'users' => $users,
            'roles' => $roles
        ]);
    }

    public function edit(User $user)
    {
        $roles = Role::latest()->get();

        return view('backend.users.edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user)
    {
        try {
            $requestData = [
                'name' => $request->name,
                'mobile' => $request->mobile,
                'dob' => $request->dob,
                'role_id' => $request->role_id ?: $user->role_id,
            ];

            if ($request->hasFile('picture')) {
                $file = $request->file('picture');
                $extension = $file->getClientOriginalExtension();
                $filename = time() . '.' . $extension;
                $file->move('images/users', $filename);
                $requestData['picture'] = $filename;
            }

            if ($request->input('change_password')) {
                $requestData['password'] = bcrypt($request->input('password'));
            }

            $user->update($requestData);

            return redirect()->route('users.index')->withMessage('Successfully Updated!');
        } catch (QueryException $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function destroy(User $user)
    {
        try {
            $user->delete();
            return redirect()->route('users.index')->withMessage('Successfully Deleted!');
        } catch (QueryException $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function show(User $user)
    {
        return view('backend.users.profiles', ['user' => $user]);
    }

    public function onlineuserlist(Request $request)
    {
        $users = User::select("*")
            ->whereNotNull('last_seen')
            ->orderBy('last_seen', 'DESC')
            ->paginate(10);

        return view('backend.users.online', ['users' => $users]);
    }

    public function  user_active($id)
    {
        $user = User::findOrFail($id);
        if ($user->is_active == 0) {
            $user->is_active = 1;
        } else {
            $user->is_active = 0;
        }
        $user->save();
        return redirect()->route('users.index');
    }
}
