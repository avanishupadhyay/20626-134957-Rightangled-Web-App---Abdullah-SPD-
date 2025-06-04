<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreConfiguration;
use App\Rules\ValidUrl;
use Illuminate\Http\Request;

class StoreController extends Controller
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

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $queryObj = Store::query();

        if ($request->isMethod('get') && $request->input('search') != '') {
            if ($request->filled('name')) {
                $queryObj->where('name', 'like', "%{$request->input('name')}%");
            }
            if ($request->filled('domain')) {
                $queryObj->where('domain', 'like', "%{$request->input('domain')}%");
            }

            if ($request->filled('status')) {
                $queryObj->where('status',  $request->input('status'));
            }
        }

        $stores   = $queryObj->paginate(config('Reading.nodes_per_page'));

        return view('admin.stores.index', compact('stores'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.stores.create');
    }

    public function validationRules($id = null)
    {
        return [
            'name'  => 'required',
            'description' => 'required',
            'domain' => 'required|url:http,https|unique:stores,domain,' . $id,
            //'domain' => ['required', 'unique:stores,domain,' . $id, new ValidUrl()],
            'image' => 'mimes:jpg,jpeg,png,gif,svg|max:2048',
            'app_client_id' => 'required|unique:stores,app_client_id,' . $id,
            'app_secret_key' => 'required|unique:stores,app_secret_key,' . $id,
            'app_admin_access_token' => 'required'
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate($this->validationRules());

        $data = $request->all();
        $data['status'] = !empty($data['status']) ? 1 : 0;
        $data['domain'] = trim($data['domain'], '/');
        Store::create($data);
        return redirect()->route('admin.stores.index')
            ->with('success', 'Store is created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Store $store)
    {
        return view('admin.stores.edit', compact('store'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Store $store)
    {
        $request->validate($this->validationRules($store->id));

        $data           = $request->all();
        $data['status'] = !empty($data['status']) ? 1 : 0;
        $data['domain'] = trim($data['domain'], '/');
        
        if ($request->hasFile('image')) {
            $image_name = $this->imageSave($request);
            $data['image']  = $image_name;
        }

        $store->update($data);

        return redirect()->route('admin.stores.index')
            ->with('success', 'Store updated successfully.');
    }


    public function destroy($id)
    {
        Store::find($id)->delete();
        return redirect()->route('admin.stores.index')
            ->with('success', 'Store deleted successfully');
    }


    /**
     * image save function
     *
     *
     **/
    private function imageSave($request)
    {
        $fileName = '';
        if (empty($request->file('image'))) {
            return $fileName;
        }

        $image = $request->file('image');


        $fileName =  $image->getClientOriginalName();
        $file_arr = explode('.', $fileName);
        $name     = $file_arr[0];
        $extension = $file_arr[1];

        $fileName = $name . '-' . time() . '.' . $extension;
        $uploadpath = storage_path('app/public/stores');
        $image->move($uploadpath, $fileName);

        return $fileName;
    }

    public function set_store(Request $request)
    {
        if ($request->isMethod('post')) {
            if (empty($request->store_id)) {
                session()->forget('store');
                return redirect()->back()->with('success', 'Store selection is reset.');
            }

            $request->validate([
                'store_id' => 'required|exists:stores,id',
            ]);


            $store = Store::find($request->store_id)->toArray();

            if (!$store) {
                return redirect()->back()->with('error', 'Store not found');
            }

            session()->put('store', $store);
            return redirect()->back()->with('success', 'Store selected successfully.');
        }

        $stores = Store::where('status', 1)->get()->toArray();

        return view('admin.stores.set_store', compact('stores'));
    }
}
