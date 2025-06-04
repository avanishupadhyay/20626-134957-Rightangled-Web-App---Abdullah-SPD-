{{-- Extends layout --}}
@extends('admin.layouts.app')

{{-- Content --}}
@section('content')

<div class="container-fluid">
    <div class="row page-titles mx-0 mb-3">
        <div class="col-sm-6 p-0">
            <div class="welcome-text">
                <h4>Store</h4>
            </div>
        </div>
        <div class="col-sm-6 p-0 justify-content-sm-end mt-2 mt-sm-0 d-flex">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.stores.index') }}">Store</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Edit</h4>
                </div>
                <div class="card-body">
                    <!-- Nav tabs -->
                    <div class="default-tab">
                        
                        <form action="{{ route('admin.stores.update', $store->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">
								<div class="col-md-6 form-group">
									<label for="StoreName">Name</label>
									<input type="text" name="name" id="Name" class="form-control" value="{{ old('name', $store->name) }}" maxlength="64">
									@error('name')
										<p class="text-danger">
											{{ $message }}
										</p>
									@enderror
								</div>
								<div class="col-md-12 form-group">
									<label for="Description">Description</label>
									<textarea name="description" id="Description" class="form-control" rows="4">{{ old('description', $store->description) }}</textarea>
                                    @error('description')
										<p class="text-danger">
											{{ $message }}
										</p>
									@enderror
								</div>
							
								<div class="col-md-6 form-group">
									<label for="title">Store URL</label>
									<input type="text" name="domain" id="domain" class="form-control" value="{{ old('domain', $store->domain) }}">
                                    @error('domain')
										<p class="text-danger">
											{{ $message }}
										</p>
									@enderror
								</div>
								
								<div class="col-md-6">
									<label for="StoreParams">Image</label>
									<div class="form-group">
                                        @if(!empty($store->image))
                                            <img src="{{ asset('storage/stores/'.$store->image) }}" alt="Store" class="img-for-onchange" width="100">
                                        @endif

                                        <div class="form-file d-inline-block">
                                            <input type="file"  accept=".png, .jpg, .jpeg"  name="image"  class="form-file-input img-input-onchange ps-2 form-control">
                                        </div>
                                        @error('image')
                                            <p class="text-danger">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
								</div>
                                	<div class="col-md-6 form-group">
									<label for="title">App Client Id</label>
									<input type="text" name="app_client_id" id="domain" class="form-control" value="{{ old('app_client_id', $store->app_client_id) }}">
                                    @error('app_client_id')
										<p class="text-danger">
											{{ $message }}
										</p>
									@enderror
								</div>
                                	<div class="col-md-6 form-group">
									<label for="title">App Secret Key</label>
									<input type="text" name="app_secret_key" id="domain" class="form-control" value="{{ old('app_secret_key', $store->app_secret_key) }}">
                                    @error('app_secret_key')
										<p class="text-danger">
											{{ $message }}
										</p>
									@enderror
								</div>
                                	<div class="col-md-6 form-group">
									<label for="title">App Admin Access Token</label>
									<input type="text" name="app_admin_access_token" id="domain" class="form-control" value="{{ old('app_admin_access_token', $store->app_admin_access_token) }}">
                                    @error('app_admin_access_token')
										<p class="text-danger">
											{{ $message }}
										</p>
									@enderror
								</div>
								<div class="col-md-6 form-group mt-4">
									<div class="custom-control custom-checkbox">
										<input type="checkbox" name="status" id="StoreStatus" class="custom-control-input  form-check-input" {{ ($store->status) ? 'checked':'' }}>
										<label class="custom-control-label" for="StoreStatus">Enabled/Disabled</label>
									</div>
								</div>
							</div>
	                                
							<div class="row">
								<div class="col-md-12 text-right">
									<button type="submit" class="btn btn-primary">Save</button>
								</div>
							</div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection