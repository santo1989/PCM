<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Division;
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
        $divisions = Division::all();
        $companies = Company::all();
        $departments  = Department::all();
        $designations  = Designation::all();
        return view('backend.users.edit', [
            'user' => $user,
            'roles' => $roles,
            'divisions' => $divisions,
            'companies' => $companies, 
            'departments' => $departments, 
            'designations' => $designations
        ]);
    }

    public function update(Request $request, User $user)
    {
        try {

            // dd($request->all());

            $requestData = [
                'name' => $request->name,
                // 'password' => $request->password ? Hash::make($request->password) : $user->password,
                'mobile' => $request->mobile,
                'division_id' => $request->division_id,
                'dob' => $request->dob,
                'joining_date' => $request->joining_date,

            ];

            if ($request->hasFile('picture')) {
                $file = $request->file('picture');
                $extension = $file->getClientOriginalExtension();
                $filename = time() . '.' . $extension;
                $file->move('images/users', $filename);
                $requestData['picture'] = $filename;
            }
            
            if($request->role_id)
            {
                $requestData['role_id'] = $request->role_id;
            }else{
                $requestData['role_id'] = $user->role_id;
            }

            if($request->company_id)
            {
                $requestData['company_id'] = $request->company_id;
            }else{
                $requestData['company_id'] = $user->company_id;
            }

            if($request->department_id)
            {
                $requestData['department_id'] = $request->department_id;
            }else{
                $requestData['department_id'] = $user->department_id;
            }

            if($request->designation_id)
            {
                $requestData['designation_id'] = $request->designation_id;
            }else{
                $requestData['designation_id'] = $user->designation_id;
            }

            if ($request->input('change_password')) {
                $user->password = bcrypt($request->input('password'));
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
        $user = User::find($user->id);
        $companies = Company::all();
        $departments  = Department::all();
        $designations  = Designation::all();
        return view('backend.users.profiles', ['user' => $user, 'companies' => $companies, 'departments' => $departments, 'designations' => $designations]);
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
