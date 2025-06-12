<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Prescriber;
use Spatie\Permission\Models\Role;
use DB;
use Hash;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{


    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->hasRole('Admin')) {
                abort(403, 'Access denied');
            }
            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $query = User::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }
        $data = $query->latest()->paginate(config('Reading.nodes_per_page'))->appends($request->all());
        $roles = \Spatie\Permission\Models\Role::pluck('name', 'name');

        return view('rbac.users.index', compact('data', 'roles'))
            ->with('i', ($request->input('page', 1) - 1) * config('Reading.nodes_per_page'));
    }

    public function create(): View
    {
       
        $roles = Role::pluck('name', 'name')->all();

        return view('rbac.users.create', compact('roles'));
    }

    public function store(Request $request) : RedirectResponse
    {

        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|same:confirm-password', 
            'roles' => 'required'
        ]);

        // Create the user first
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign roles to the user
        $user->assignRole($request->input('roles'));

        // Handle signature image upload
        $signatureImage = null;
        if ($request->hasFile('signature')) {
            $signatureImage = $this->imageSave($request);
        }
        // Create the prescriber record
        Prescriber::create([
            'user_id' => $user->id,
            'gphc_number' => $request->gphc_number ?? '',
            'signature_image' => $signatureImage,
        ]);
        
        return redirect()->route('users.index')
            ->with('success', 'User created successfully');
    }


    public function edit($id): View
    {
        $user = User::find($id);
        $roles = Role::pluck('name', 'name')->all();
        $prescriber = Prescriber::where('user_id',$id)->first();
        $userRole = $user->roles->pluck('name', 'name')->all();
    
        return view('rbac.users.edit', compact('user', 'roles', 'userRole','prescriber'));
    }


      public function update(Request $request, $id): RedirectResponse
    {
       
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'same:confirm-password',
            'roles' => 'required',
            'status' => 'required',
        ]);

        $input = $request->all();
        if (!empty($input['password'])) {
            $input['password'] = \Hash::make($input['password']);
        } else {
            $input = Arr::except($input, array('password'));
        }
     
        if(isset($input['gphc_number']) && isset($input['signature']) && !empty($input['gphc_number']) && !empty($input['signature'])){
            unset($input['gphc_number']);
            unset($input['signature']);
        }
 
        $user = User::find($id);
        $user->update($input);
        \DB::table('model_has_roles')->where('model_id', $id)->delete();

        $user->assignRole($request->input('roles'));

       if ($request->has('gphc_number') || $request->hasFile('signature')) {
            $data = [];

            if ($request->filled('gphc_number')) {
                $data['gphc_number'] = $request->gphc_number;
            }

            if ($request->hasFile('signature')) {
                $data['signature_image'] = $this->imageSave($request);
            }

            if (!empty($data)) {
                Prescriber::updateOrCreate(
                    ['user_id' => $user->id],
                    $data
                );
            }
        }



        return redirect()->route('users.index')
            ->with('success', 'User updated successfully');
    }

    public function destroy($id): RedirectResponse
    {
        User::find($id)->delete();
        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully');
    }

    private function imageSave($request)
    {
        $fileName = '';

        // Check if file exists in request
        if (!$request->hasFile('signature')) {
            return $fileName;
        }

        $image = $request->file('signature');

        // Generate a unique filename
        $originalName = $image->getClientOriginalName();
        $extension = $image->getClientOriginalExtension();
        $name = pathinfo($originalName, PATHINFO_FILENAME); // Get filename without extension
        
        $fileName = $name . '-' . time() . '.' . $extension;

        // Define upload path (inside public folder)
        $uploadPath = public_path('admin/signature-images');

        // Create directory if it doesn't exist
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true); // 0755 = directory permissions
        }

        // Move the file to the public path
        $image->move($uploadPath, $fileName);

        // Return the relative path (e.g., 'admin/signature-images/filename.jpg')
        return  $fileName;
    }
}